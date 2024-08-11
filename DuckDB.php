<?php
namespace App\Libraries;

if (!extension_loaded('FFI')) {
    throw new \Exception('Ekstensi FFI tidak tersedia. Pastikan FFI diaktifkan di konfigurasi PHP Anda.');
}
use \FFI;
class DuckDB{
    private FFI $ffi;
    private FFI\CData $db;
    private FFI\CData $conn;

    private $type;

    public function __construct(string $databasePath){
        $this->type = [
            0=>'INVALID',
            1=>'BOOLEAN',
            2=>'TINYINT',
            3=>'SMALLINT',
            4=>'INTEGER',
            5=>'BIGINT',
            6=>'UTINYINT',
            7=>'USMALLINT',
            8=>'UINTEGER',
            9=>'UBIGINT',
            10=>'FLOAT',
            11=>'DOUBLE',
            12=>'TIMESTAMP',
            13=>'DATE',
            14=>'TIME',
            15=>'INTERVAL',
            16=>'HUGEINT',
            17=>'VARCHAR',
            18=>'BLOB',
            19=>'DECIMAL',
            20=>'TIMESTAMP_S',
            21=>'TIMESTAMP_MS',
            22=>'TIMESTAMP_NS',
            23=>'ENUM',
            24=>'LIST',
            25=>'STRUCT',
            26=>'MAP',
            27=>'UUID',
            28=>'UNION',
            29=>'BIT',
            30=>'TIME_TZ',
            31=>'TIMESTAMP_TZ',
            32=>'UHUGEINT',
            33=>'ARRAY',
        ];
        try{
            $header = file_get_contents(__DIR__.'/myduck.h');
            $lib_so = __DIR__.'/libduckdb.so';
            $this->ffi = FFI::cdef($header,$lib_so);
            $this->db = $this->ffi->new('duckdb_database');
            $this->conn = $this->ffi->new('duckdb_connection');
            $this->ffi->duckdb_open($databasePath, FFI::addr($this->db));
            $this->ffi->duckdb_connect($this->db, FFI::addr($this->conn));
        }catch(\Exception $ex){
            return $ex->getMessage();
        }
    }
    public function __destruct()
    {
        $this->ffi->duckdb_disconnect(FFI::addr($this->conn));
        $this->ffi->duckdb_close(FFI::addr($this->db));
        FFI::free($this->conn);
        FFI::free($this->db);
        unset($this->ffi);
    }
    public function query(string $query){
        try{
            $result = $this->ffi->new('duckdb_result');
            $this->ffi->duckdb_query($this->conn, $query, FFI::addr($result));
            
            $jmlCol = $this->ffi->duckdb_column_count(FFI::addr($result));
            $jmlRow = $this->ffi->duckdb_row_count(FFI::addr($result));
            if($jmlRow<1){
                $this->ffi->duckdb_destroy_result(FFI::addr($result));
                return null;
            }
            $data = [];
            $column = [];
            for ($col=0;$col<$jmlCol;$col++) {
                $colName = $this->ffi->duckdb_column_name(FFI::addr($result),$col);
                $colType = $this->ffi->duckdb_column_type(FFI::addr($result),$col);
                $column[] = [
                    'type'=>$this->type[$colType],
                    'name'=>$colName
                ];
                for ($row=0;$row<$jmlRow;$row++) {
                    $value = $this->ffi->duckdb_value_varchar(FFI::addr($result), $col, $row);
                    $data[$row][$colName] = FFI::string($value);
                    FFI::free($value);
                }
                //FFI::free($colName);
            }
            
            $this->ffi->duckdb_destroy_result(FFI::addr($result));
            return $data;
        }catch(\Exception $ex){
            return $ex->getMessage();
        }
    }
}