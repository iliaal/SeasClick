/*
  +----------------------------------------------------------------------+
  | php_clickhouse                                                       |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2026 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_01.txt                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |
  +----------------------------------------------------------------------+
*/

/* Shared internals for the clickhouse extension translation units
 * (clickhouse.cpp, query_log.cpp, metadata.cpp, ...).
 *
 * Include order (mirrors clickhouse.cpp): PHP headers in extern "C",
 * then clickhouse-cpp client/type headers, then typesToPhp.hpp, then
 * this file. This header assumes zend/zval types and
 * clickhouse::Client/ClientOptions/Block names are visible.
 */

#ifndef PHP_CLICKHOUSE_INTERNAL_H
#define PHP_CLICKHOUSE_INTERNAL_H

#include <chrono>
#include <deque>
#include <string>
#include <unordered_map>
#include <vector>

extern zend_class_entry *clickhouse_ce, *clickhouse_exception_ce, *clickhouse_iter_ce, *clickhouse_statement_ce;

struct ClientStats {
    uint64_t rows_read = 0;
    uint64_t bytes_read = 0;
    uint64_t total_rows = 0;
    uint64_t written_rows = 0;
    uint64_t written_bytes = 0;
    uint64_t blocks = 0;
    uint64_t rows_before_limit = 0;
    bool applied_limit = false;
    double elapsed_ms = 0.0;
    std::string last_query_id;
};
struct QueryLog {
    std::string sql;
    std::string query_id;
    double elapsed_ms = 0.0;
    uint64_t rows_read = 0;
    uint64_t bytes_read = 0;
    int error_code = 0;            // 0 = success; ServerException code on server failure; -1 on client/network failure
    std::string error_message;
};

/*
 * Per-Client state lives on the zend_object itself. Replaces the old
 * file-scope std::map<int, ...> bank keyed on Z_OBJ_HANDLE. Two
 * concrete benefits over the old layout: free_obj fires even on
 * bailout (so a fatal error mid-query no longer leaks the Client*),
 * and ZTS works naturally because there is no global state to
 * thread-isolate.
 *
 * The std member must be last; create_object placement-news the
 * non-POD members and free_obj placement-destructs them.
 */
struct clickhouse_object {
    clickhouse::Client *client;
    clickhouse::Block insert_block;
    bool has_insert_block;
    /* Guards same-client reentry. clickhouse-cpp's Client uses a single
     * socket and a single per-call packet loop; a userland callback that
     * fires another query / insert on the same client mid-stream sends
     * packets into a wire still owned by the outer call and crashes the
     * worker on the next ReceiveData. Set true at the start of each
     * client-touching method, cleared on exit. Reentry throws cleanly. */
    bool query_active;
    ClientStats stats;
    std::unordered_map<std::string, std::string> settings;
    zval progress_callback;        // IS_UNDEF when unset
    zval profile_callback;         // IS_UNDEF when unset
    zval verbose_callback;         // IS_UNDEF when off or stderr-mode
    bool verbose_to_stderr;        // true when setVerbose(true) was used
    bool log_enabled;
    std::string insert_sql;
    std::string insert_query_id;
    std::chrono::steady_clock::time_point insert_started_at;
    /* deque, not vector, so the cap-overflow path's pop-front is O(1).
     * vector::erase(begin()) on a 1024-entry log shifted ~16 KB on
     * every query past the cap. */
    std::deque<QueryLog> query_log;
    /* The exact ClientOptions the live Client was built with. setDatabase()
     * rebuilds the Client from these (with default_database swapped) rather
     * than issuing a session `USE`, so the new database survives every
     * reconnect — including clickhouse-cpp's internal RetryGuard reconnect
     * (ping_before_query) which the extension never sees and could not
     * re-apply a USE to. */
    clickhouse::ClientOptions client_options;
#if PHP_VERSION_ID < 80000
    /* PHP 7.4 has no zend_get_gc_buffer; get_gc must point *table at a
     * buffer that outlives the call. Three slots for the three callbacks. */
    zval gc_buf[3];
#endif
    zend_object std;
};

static inline clickhouse_object *clickhouse_from_obj(zend_object *obj)
{
    return (clickhouse_object *)((char *)obj - offsetof(clickhouse_object, std));
}

#define Z_CLICKHOUSE_P(zv) clickhouse_from_obj(Z_OBJ_P(zv))

/* ---- query_log.cpp (owns the query-log ring + redaction) ---- */
std::string queryLogString(const std::string &value);
std::string redactSqlLiterals(const std::string &sql);
void appendQueryLogCapped(clickhouse_object *obj, QueryLog &&ql);
QueryLog buildQueryLog(const clickhouse_object *obj,
                       const std::string &sql, const std::string &qid);
void recordQuerySuccess(clickhouse_object *obj, const std::string &sql, const std::string &qid);
void recordQueryError(clickhouse_object *obj, const std::string &sql,
                      const std::string &qid, const std::exception &e);

/* ---- defined in clickhouse.cpp, shared across TUs ---- */
std::string sanitizeError(const char *what);
void addAssocUInt64(zval *array, const char *key, uint64_t value);

/* ---- metadata.cpp (identifier helpers + metadata helpers/selects) ---- */
std::string sqlStringLiteral(const std::string &s);
std::string sqlQuotedIdentifier(const std::string &s);
void parseIdentifier(const char *s, size_t len, const char *what,
                     bool allow_dot, std::string *emit_out);
void validateIdentifier(const char *s, size_t len, const char *what, bool allow_dot);
void validateMetadataFilterName(const std::string &s, const char *what);
std::string currentDatabase(zval *this_obj);
void runHelperSelect(zval *return_value, zval *this_obj,
                     const std::string &sql, zend_long fetch_mode);
bool runHelperExec(zval *this_obj, const std::string &sql);
void runHelperSelectFirstRow(zval *return_value, zval *this_obj, const std::string &sql);
void throwClickHouseError(const std::exception &e, const std::string &query_id = std::string());
void do_select_into(zval *out, zval *this_obj,
                    const char *sql, size_t l_sql,
                    zval *params, zend_long fetch_mode,
                    const std::string &qid, zval *settings,
                    const clickhouse::ExternalTables *external_tables,
                    zval *positional_out);
void do_execute_into(zval *this_obj,
                     const char *sql, size_t l_sql,
                     zval *params, const std::string &qid, zval *settings);

#endif /* PHP_CLICKHOUSE_INTERNAL_H */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
