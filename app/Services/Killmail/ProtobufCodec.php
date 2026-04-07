<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Log;

/**
 * Protobuf 编解码工具类
 * 
 * 用于解析 Beta KB API 的 Protobuf 响应和构造 Protobuf 请求
 */
class ProtobufCodec
{
    // ========================================================
    // Protobuf 解码工具 (用于解析 beta KB API 响应)
    // ========================================================

    /**
     * 解码 Varint
     */
    public function decodeVarint(string $data, int $offset, int &$newOffset): int
    {
        $result = 0;
        $shift = 0;
        $len = strlen($data);
        while ($offset < $len) {
            $byte = ord($data[$offset]);
            $result |= ($byte & 0x7F) << $shift;
            $offset++;
            $shift += 7;
            if (($byte & 0x80) === 0) break;
        }
        $newOffset = $offset;
        return $result;
    }

    /**
     * 解析 Protobuf 消息
     */
    public function parseMessage(string $data): array
    {
        $fields = [];
        $offset = 0;
        $len = strlen($data);

        while ($offset < $len) {
            if ($offset >= $len) break;
            $tag = $this->decodeVarint($data, $offset, $offset);
            $fieldNum = $tag >> 3;
            $wireType = $tag & 0x07;

            switch ($wireType) {
                case 0:
                    $value = $this->decodeVarint($data, $offset, $offset);
                    break;
                case 1:
                    if ($offset + 8 > $len) {
                        Log::debug("Protobuf解析: 数据不完整(wire=1), offset={$offset}, len={$len}, 需要8字节");
                        return $fields;
                    }
                    $value = substr($data, $offset, 8);
                    $offset += 8;
                    break;
                case 2:
                    $length = $this->decodeVarint($data, $offset, $offset);
                    if ($offset + $length > $len) {
                        Log::debug("Protobuf解析: 数据不完整(wire=2), offset={$offset}, len={$len}, 需要{$length}字节");
                        return $fields;
                    }
                    $value = substr($data, $offset, $length);
                    $offset += $length;
                    break;
                case 5:
                    if ($offset + 4 > $len) {
                        Log::debug("Protobuf解析: 数据不完整(wire=5), offset={$offset}, len={$len}, 需要4字节");
                        return $fields;
                    }
                    $value = substr($data, $offset, 4);
                    $offset += 4;
                    break;
                default:
                    Log::debug("Protobuf解析: 未知wire类型, wireType={$wireType}");
                    return $fields;
            }

            $fields[] = ['field' => $fieldNum, 'wire' => $wireType, 'value' => $value];
        }
        return $fields;
    }

    /**
     * 从解析后的字段中获取 Varint 值
     */
    public function getVarint(array $fields, int $fieldNum): ?int
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 0) {
                return (int) $f['value'];
            }
        }
        return null;
    }

    /**
     * 从解析后的字段中获取字符串值
     */
    public function getString(array $fields, int $fieldNum): ?string
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 2) {
                $inner = $f['value'];
                if (preg_match('/^[\x20-\x7E\xC0-\xFF][\x20-\x7E\x80-\xFF]*$/s', $inner) && mb_check_encoding($inner, 'UTF-8')) {
                    return $inner;
                }
                $sub = $this->parseMessage($inner);
                foreach ($sub as $sf) {
                    if ($sf['field'] === 1 && $sf['wire'] === 2 && mb_check_encoding($sf['value'], 'UTF-8')) {
                        return $sf['value'];
                    }
                }
                return null;
            }
        }
        return null;
    }

    /**
     * 从解析后的字段中获取 Double 值
     */
    public function getDouble(array $fields, int $fieldNum): ?float
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 1 && strlen($f['value']) === 8) {
                return unpack('e', $f['value'])[1];
            }
        }
        return null;
    }

    // ========================================================
    // Protobuf 编码工具 (用于构造 beta KB search API 请求)
    // ========================================================

    /**
     * 编码 Varint
     */
    public function encodeVarint(int $val): string
    {
        $bytes = '';
        if ($val === 0) return chr(0);
        while ($val > 0) {
            $byte = $val & 0x7F;
            $val >>= 7;
            if ($val > 0) $byte |= 0x80;
            $bytes .= chr($byte);
        }
        return $bytes;
    }

    /**
     * 编码字符串字段
     */
    public function encodeString(int $fieldNum, string $str): string
    {
        return $this->encodeVarint(($fieldNum << 3) | 2) . $this->encodeVarint(strlen($str)) . $str;
    }

    /**
     * 编码 Packed Int64 数组
     */
    public function encodePackedInt64(int $fieldNum, array $values): string
    {
        $packed = '';
        foreach ($values as $v) {
            $packed .= $this->encodeVarint($v);
        }
        return $this->encodeVarint(($fieldNum << 3) | 2) . $this->encodeVarint(strlen($packed)) . $packed;
    }

    /**
     * 编码嵌套消息 (wire type 2)
     */
    public function encodeMessage(int $fieldNum, string $inner): string
    {
        return $this->encodeVarint(($fieldNum << 3) | 2) . $this->encodeVarint(strlen($inner)) . $inner;
    }

    /**
     * 编码 Google Timestamp (seconds since epoch)
     */
    public function encodeTimestamp(int $fieldNum, int $seconds): string
    {
        $inner = $this->encodeVarint((1 << 3) | 0) . $this->encodeVarint($seconds);
        return $this->encodeMessage($fieldNum, $inner);
    }
}
