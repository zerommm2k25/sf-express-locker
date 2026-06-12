<?php
defined( 'ABSPATH' ) || exit;

class SF_Locker_Data {

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            type VARCHAR(10) DEFAULT 'LOCKER',
            name_zh VARCHAR(255) DEFAULT '',
            address_zh TEXT NOT NULL,
            district VARCHAR(100) DEFAULT '',
            region VARCHAR(50) DEFAULT '',
            opening_hours VARCHAR(255) DEFAULT '',
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function import_bundled_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        if ( $count > 0 ) {
            return;
        }

        $file = SF_LOCKER_PATH . 'data/lockers.json';
        if ( ! file_exists( $file ) ) {
            return;
        }

        $json = file_get_contents( $file );
        $lockers = json_decode( $json, true );

        if ( empty( $lockers ) ) {
            return;
        }

        foreach ( $lockers as $locker ) {
            $wpdb->replace( $table_name, array(
                'code'          => $locker['code'],
                'type'          => $locker['type'] ?? 'LOCKER',
                'name_zh'       => $locker['name_zh'] ?? '',
                'address_zh'    => $locker['address_zh'],
                'district'      => $locker['district'] ?? '',
                'region'        => $locker['region'] ?? '',
                'opening_hours' => $locker['opening_hours'] ?? '',
                'latitude'      => ! empty( $locker['latitude'] ) ? $locker['latitude'] : null,
                'longitude'     => ! empty( $locker['longitude'] ) ? $locker['longitude'] : null,
                'is_active'     => 1,
            ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d' ) );
        }
    }

    public static function search( $keyword = '', $district = '', $limit = 20, $region = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;

        $where = 'WHERE is_active = 1 AND type = %s';
        $params = array( 'LOCKER' );

        if ( ! empty( $keyword ) ) {
            $where .= ' AND (address_zh LIKE %s OR district LIKE %s OR code LIKE %s)';
            $like = '%' . $wpdb->esc_like( $keyword ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( ! empty( $district ) ) {
            $where .= ' AND district = %s';
            $params[] = $district;
        }

        if ( ! empty( $region ) ) {
            $where .= ' AND region = %s';
            $params[] = $region;
        }

        $sql = "SELECT * FROM {$table_name} {$where} ORDER BY district ASC, address_zh ASC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
    }

    public static function get_by_code( $code ) {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE code = %s LIMIT 1", $code
        ), ARRAY_A );
    }

    public static function get_districts() {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        return $wpdb->get_col(
            "SELECT DISTINCT district FROM {$table_name} WHERE is_active = 1 AND district != '' ORDER BY district ASC"
        );
    }

    public static function get_districts_by_region( $region ) {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT district FROM {$table_name} WHERE is_active = 1 AND region = %s AND district != '' ORDER BY district ASC",
            $region
        ) );
    }

    public static function get_regions() {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        return $wpdb->get_col(
            "SELECT DISTINCT region FROM {$table_name} WHERE is_active = 1 AND region != '' ORDER BY region ASC"
        );
    }

    public static function get_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }

    public static function get_all( $offset = 0, $limit = 100 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY region, district, address_zh ASC LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A );
    }

    public static function import_batch( $lockers, $force_table_check = true ) {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;

        if ( $force_table_check ) {
            self::maybe_create_table();
        }

        if ( ! self::table_exists() ) {
            return array( 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => '資料表不存在，無法匯入。' );
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ( $lockers as $locker ) {
            if ( empty( $locker['code'] ) || empty( $locker['address_zh'] ) ) {
                $skipped++;
                continue;
            }

            $data = array(
                'code'          => $locker['code'],
                'type'          => $locker['type'] ?? 'LOCKER',
                'name_zh'       => $locker['name_zh'] ?? '',
                'address_zh'    => $locker['address_zh'],
                'district'      => $locker['district'] ?? '',
                'region'        => $locker['region'] ?? '',
                'opening_hours' => $locker['opening_hours'] ?? '',
                'latitude'      => ! empty( $locker['latitude'] ) ? $locker['latitude'] : null,
                'longitude'     => ! empty( $locker['longitude'] ) ? $locker['longitude'] : null,
                'is_active'     => 1,
            );

            $formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d' );

            $result = $wpdb->replace( $table_name, $data, $formats );
            if ( false === $result ) {
                $skipped++;
            } elseif ( $result === 1 ) {
                $inserted++;
            } else {
                $updated++;
            }
        }

        $result = compact( 'inserted', 'updated', 'skipped' );
        if ( $wpdb->last_error ) {
            $result['error'] = $wpdb->last_error;
        }
        return $result;
    }

    private static function maybe_create_table() {
        if ( ! self::table_exists() ) {
            self::create_table();
        }
    }

    private static function table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        $check = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        return $check === $table_name;
    }
}
