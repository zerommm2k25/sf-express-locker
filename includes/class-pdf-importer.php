<?php
defined( 'ABSPATH' ) || exit;

class SF_Locker_PDF_Importer {

    private $parser;
    private $errors = array();

    public function __construct() {
        if ( class_exists( 'Smalot\PdfParser\Parser' ) ) {
            $this->parser = new Smalot\PdfParser\Parser();
        }
    }

    public function is_available() {
        return $this->parser !== null;
    }

    public function parse( $file_path ) {
        $this->errors = array();

        if ( ! file_exists( $file_path ) ) {
            $this->errors[] = '檔案不存在。';
            return false;
        }

        if ( ! $this->parser ) {
            $this->errors[] = 'PDF 解析器未載入。請確認已安裝 smalot/pdfparser。';
            return false;
        }

        try {
            $pdf = $this->parser->parseFile( $file_path );
            $pages = $pdf->getPages();
            $lockers = array();
            $current_region = '';
            $current_district = '';
            $buffer = '';

            foreach ( $pages as $page ) {
                $text = $page->getText();
                $lines = explode( "\n", $text );

                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( empty( $line ) ) {
                        if ( ! empty( $buffer ) ) {
                            $result = self::parse_locker_line( $buffer, $current_region, $current_district );
                            if ( $result ) {
                                $lockers[] = $result;
                            }
                            $buffer = '';
                        }
                        continue;
                    }

                    if ( preg_match( '/^(香港島|九龍|新界|離島)$/u', $line, $m ) ) {
                        if ( ! empty( $buffer ) ) {
                            $result = self::parse_locker_line( $buffer, $current_region, $current_district );
                            if ( $result ) {
                                $lockers[] = $result;
                            }
                            $buffer = '';
                        }
                        $current_region = self::normalize_region( $m[1] );
                        $current_district = '';
                        continue;
                    }

                    $known_districts = $this->get_known_districts();

                    $is_district = false;
                    foreach ( $known_districts as $d ) {
                        if ( $line === $d || preg_match( '/^' . preg_quote( $d, '/' ) . '[\s　]/u', $line ) ) {
                            $current_district = $d;
                            $is_district = true;
                            $remainder = preg_replace( '/^' . preg_quote( $d, '/' ) . '[\s　]*/u', '', $line );
                            if ( preg_match( '/^H\d{2,3}[A-Z0-9]{2,5}P?\s+/u', $remainder ) ) {
                                if ( ! empty( $buffer ) ) {
                                    $result = self::parse_locker_line( $buffer, $current_region, $current_district );
                                    if ( $result ) {
                                        $lockers[] = $result;
                                    }
                                }
                                $buffer = $remainder;
                            }
                            break;
                        }
                    }
                    if ( $is_district ) {
                        continue;
                    }

                    if ( preg_match( '/^\d{2}:\d{2}/', $line ) || $line === '24小時 24小時' || $line === '24小時' ) {
                        continue;
                    }

                    if ( $line === '（星期一至六）' || $line === '（星期一至六)' || $line === '六)' || $line === '六）' ) {
                        continue;
                    }
                    if ( $line === '（星期日/公眾假期）' || $line === '（星期日／公眾假期）' || $line === '（星期日／公眾假' || $line === '（星期日/公眾假' ) {
                        continue;
                    }
                    if ( preg_match( '/^12:\d{2}-18:\d{2}$/', $line ) ) {
                        continue;
                    }

                    if ( preg_match( '/^H\d{2,3}[A-Z0-9]{2,5}P?\s+/u', $line, $m ) ) {
                        if ( ! empty( $buffer ) ) {
                            $result = self::parse_locker_line( $buffer, $current_region, $current_district );
                            if ( $result ) {
                                $lockers[] = $result;
                            }
                        }
                        $buffer = $line;
                    } elseif ( ! empty( $buffer ) ) {
                        $buffer .= ' ' . $line;
                    }
                }

                if ( ! empty( $buffer ) ) {
                    $result = self::parse_locker_line( $buffer, $current_region, $current_district );
                    if ( $result ) {
                        $lockers[] = $result;
                    }
                    $buffer = '';
                }
            }

            $unique = array();
            $seen = array();
            foreach ( $lockers as $l ) {
                if ( ! isset( $seen[ $l['code'] ] ) ) {
                    $seen[ $l['code'] ] = true;
                    $unique[] = $l;
                }
            }

            if ( empty( $unique ) ) {
                $this->errors[] = '無法從 PDF 中解析出任何智能櫃資料。請確認檔案格式正確。';
                return false;
            }

            return $unique;

        } catch ( Exception $e ) {
            $this->errors[] = '解析 PDF 時出錯：' . $e->getMessage();
            return false;
        }
    }

    private static function parse_locker_line( $line, $current_region, $current_district ) {
        if ( ! preg_match( '/^(H\d{2,3}[A-Z0-9]{2,5}P?)[\s　]*(.+)$/u', $line, $m ) ) {
            return null;
        }

        $code = $m[1];
        $rest = $m[2];

        $parts = preg_split( '/\t+/', $rest, 3 );

        $address = '';
        $hours = '';

        if ( count( $parts ) >= 3 ) {
            $address = trim( $parts[0] );
            $hours_mon_sat = trim( $parts[1] );
            $hours_sun = trim( $parts[2] );
            $hours = trim( $hours_mon_sat . ' / ' . $hours_sun, ' /' );
            if ( $hours === '24小時 / 24小時' ) {
                $hours = '24小時';
            }
        } elseif ( count( $parts ) == 2 ) {
            $address = trim( $parts[0] );
            $hours_all = trim( $parts[1] );
            if ( preg_match( '/^(\d{2}:\d{2}-\d{2}:\d{2})\s+(\d{2}:\d{2}-\d{2}:\d{2})$/', $hours_all, $hm ) ) {
                $hours = $hm[1] . ' / ' . $hm[2];
            } else {
                $hours = $hours_all;
                if ( $hours === '24小時 24小時' ) {
                    $hours = '24小時';
                }
            }
        } elseif ( count( $parts ) == 1 ) {
            $address = trim( $parts[0] );
        }

        $address = preg_replace( '/\s*\(.*?\)\s*$/', '', $address );
        $address = preg_replace( '/\s+(?:2[34]小[時时](?:\s+2[34]小[時时])?|\d{2}:\d{2}-\d{2}:\d{2}\s+\d{2}:\d{2}-\d{2}:\d{2})\s*$/u', '', $address );
        $address = trim( $address );

        if ( empty( $address ) || empty( $code ) ) {
            return null;
        }

        return array(
            'code'          => $code,
            'type'          => 'LOCKER',
            'name_zh'       => '',
            'address_zh'    => $address,
            'district'      => $current_district,
            'region'        => $current_region,
            'opening_hours' => $hours,
            'latitude'      => null,
            'longitude'     => null,
        );
    }

    public function import( $file_path ) {
        $lockers = $this->parse( $file_path );
        if ( false === $lockers ) {
            return false;
        }

        return SF_Locker_Data::import_batch( $lockers );
    }

    public function get_errors() {
        return $this->errors;
    }

    private function get_known_districts() {
        return array(
            // 香港島 — section headers (18) + inline-only (4)
            '堅尼地城', '西營盤', '上環', '半山區', '鴨脷洲',
            '灣仔', '銅鑼灣', '大坑', '天后', '北角',
            '鰂魚涌', '西灣河', '筲箕灣', '柴灣', '小西灣',
            '香港仔', '赤柱', '薄扶林',
            '西環', '杏花邨', '中環', '黃竹坑',
            // 九龍 — section headers (29) + inline-only (2)
            '九龍城', '啟德', '九龍塘', '土瓜灣', '紅磡',
            '何文田', '太子', '大角咀', '旺角', '油麻地',
            '佐敦', '尖沙咀', '深水埗', '長沙灣', '荔枝角',
            '美孚', '南昌', '新蒲崗', '慈雲山', '黃大仙',
            '樂富', '彩虹', '牛池灣', '九龍灣', '牛頭角',
            '觀塘', '秀茂坪', '藍田', '油塘',
            '石硤尾', '鑽石山',
            // 新界 — section headers (20) + inline-only (5)
            '大圍', '沙田', '火炭', '馬鞍山', '大埔',
            '粉嶺', '上水', '元朗', '天水圍', '屯門',
            '深井', '荃灣', '大窩口', '葵芳', '葵涌',
            '青衣', '東涌', '調景嶺', '將軍澳', '西貢',
            '馬灣', '青龍頭', '梅窩', '貝澳', '赤臘角',
        );
    }

    private static function normalize_region( $name ) {
        $map = array(
            '香港島' => 'HK',
            '九龍'   => 'KLN',
            '新界'   => 'NT',
            '離島'   => 'IS',
        );
        return isset( $map[ $name ] ) ? $map[ $name ] : $name;
    }
}
