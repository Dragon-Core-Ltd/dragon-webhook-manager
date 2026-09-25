<?php
/**
 * In-memory stand-in for $wpdb covering the calls the Pro store classes make.
 *
 * prepare() packs the query and its arguments as JSON so the read methods can
 * match on the query shape and pull the bound values back out.
 *
 * @package DragonWebhookManager
 */

namespace DragonWebhookManager\Tests;

/**
 * Minimal $wpdb double.
 */
class FakeWpdb {

	public string $prefix = 'wp_';

	public int $insert_id = 0;

	/**
	 * Table names reported by SHOW TABLES.
	 *
	 * @var string[]
	 */
	public array $tables = array();

	/**
	 * Rows by table name.
	 *
	 * @var array<string, array<int, array>>
	 */
	public array $rows = array();

	public bool $fail_writes = false;

	/**
	 * Every read/query the code under test issued, decoded to q/a.
	 *
	 * @var array<int, array>
	 */
	public array $queries = array();

	/**
	 * When not null, query() returns this instead of running.
	 *
	 * @var mixed
	 */
	public $query_result = null;

	private int $next_id = 1;

	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		return json_encode( array( 'q' => $query, 'a' => array_values( $args ) ) );
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	public function get_charset_collate(): string {
		return '';
	}

	private static function decode( string $sql ): array {
		$decoded = json_decode( $sql, true );
		return is_array( $decoded ) ? $decoded : array( 'q' => $sql, 'a' => array() );
	}

	public function get_var( string $sql ) {
		$p               = self::decode( $sql );
		$this->queries[] = $p;
		$q = $p['q'];
		$a = $p['a'];

		if ( str_starts_with( $q, 'SHOW TABLES LIKE' ) ) {
			$name = stripcslashes( (string) $a[0] );
			return in_array( $name, $this->tables, true ) ? $name : null;
		}

		if ( str_starts_with( $q, 'SELECT item_value FROM' ) || str_starts_with( $q, 'SELECT id FROM' ) ) {
			list( $table, $type, $id, $key ) = $a;
			foreach ( $this->rows[ $table ] ?? array() as $row ) {
				if ( $row['object_type'] === $type && (int) $row['object_id'] === (int) $id && $row['item_key'] === $key ) {
					return str_starts_with( $q, 'SELECT id' ) ? $row['id'] : $row['item_value'];
				}
			}
			return null;
		}

		return null;
	}

	public function get_row( string $sql, $output = ARRAY_A ) {
		$p = self::decode( $sql );
		if ( 'SELECT * FROM %i WHERE id = %d' === $p['q'] ) {
			list( $table, $id ) = $p['a'];
			foreach ( $this->rows[ $table ] ?? array() as $row ) {
				if ( (int) $row['id'] === (int) $id ) {
					return $row;
				}
			}
			return null;
		}
		if ( str_starts_with( $p['q'], 'SELECT * FROM %i WHERE event_id' ) ) {
			list( $table, $event_id ) = $p['a'];
			$match = null;
			foreach ( $this->rows[ $table ] ?? array() as $row ) {
				if ( (int) $row['event_id'] === (int) $event_id ) {
					$match = $row;
				}
			}
			return $match;
		}
		return null;
	}

	public function insert( string $table, array $data, $format = null ) {
		if ( $this->fail_writes ) {
			return false;
		}
		$data['id']              = $this->next_id++;
		$this->rows[ $table ][]  = $data;
		$this->insert_id         = $data['id'];
		return 1;
	}

	public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		if ( $this->fail_writes ) {
			return false;
		}
		$n = 0;
		foreach ( $this->rows[ $table ] ?? array() as $i => $row ) {
			foreach ( $where as $k => $v ) {
				if ( (string) $row[ $k ] !== (string) $v ) {
					continue 2;
				}
			}
			$this->rows[ $table ][ $i ] = array_merge( $row, $data );
			++$n;
		}
		return $n;
	}

	public function delete( string $table, array $where, $format = null ) {
		$n = 0;
		foreach ( $this->rows[ $table ] ?? array() as $i => $row ) {
			foreach ( $where as $k => $v ) {
				if ( (string) $row[ $k ] !== (string) $v ) {
					continue 2;
				}
			}
			unset( $this->rows[ $table ][ $i ] );
			++$n;
		}
		return $n;
	}

	public function query( string $sql ) {
		$p               = self::decode( $sql );
		$this->queries[] = $p;
		if ( null !== $this->query_result ) {
			return $this->query_result;
		}
		// Mirrors MySQL: DELETE reports affected rows, TRUNCATE reports true.
		if ( 'DELETE FROM %i' === $p['q'] || 'TRUNCATE TABLE %i' === $p['q'] ) {
			$table = $p['a'][0];
			$n     = count( $this->rows[ $table ] ?? array() );
			$this->rows[ $table ] = array();
			return 'DELETE FROM %i' === $p['q'] ? $n : true;
		}
		return 0;
	}
}
