<?php
/**
 * VivalaTable Meta System
 * Metadata storage for objects
 */

class VT_Meta {

    /**
     * Add metadata
     */
    public static function add($objectType, $objectId, $metaKey, $metaValue, $unique = false) {
        return self::update($objectType, $objectId, $metaKey, $metaValue, !$unique);
    }

    /**
     * Update metadata
     */
    public static function update($objectType, $objectId, $metaKey, $metaValue, $allowDuplicates = false) {
        $db = VT_Database::getInstance();
        $table = $db->prefix . 'meta';

        // For now, we'll implement a simple approach
        // In production, you might want separate meta tables for different object types

        if (!$allowDuplicates) {
            // Remove existing entries first
            self::delete($objectType, $objectId, $metaKey);
        }

        $result = $db->insert('meta', array(
            'object_type' => vt_service('validation.sanitizer')->textField($objectType),
            'object_id' => intval($objectId),
            'meta_key' => vt_service('validation.sanitizer')->textField($metaKey),
            'meta_value' => maybe_serialize($metaValue),
            'created_at' => VT_Time::currentTime('mysql')
        ));

        return $result ? $db->insert_id : false;
    }

    /**
     * Get metadata
     */
    public static function get($objectType, $objectId, $metaKey = '', $single = false) {
        $db = VT_Database::getInstance();
        $table = $db->prefix . 'meta';

        if (empty($metaKey)) {
            // Get all meta for this object
            $results = $db->getResults(
                $db->prepare(
                    "SELECT meta_key, meta_value FROM $table WHERE object_type = %s AND object_id = %d",
                    $objectType, $objectId
                )
            );

            $meta = array();
            foreach ($results as $row) {
                $meta[$row->meta_key][] = maybe_unserialize($row->meta_value);
            }

            return $meta;
        } else {
            // Get specific meta key
            if ($single) {
                $result = $db->getVar(
                    $db->prepare(
                        "SELECT meta_value FROM $table WHERE object_type = %s AND object_id = %d AND meta_key = %s LIMIT 1",
                        $objectType, $objectId, $metaKey
                    )
                );
                return maybe_unserialize($result);
            } else {
                $results = $db->getCol(
                    $db->prepare(
                        "SELECT meta_value FROM $table WHERE object_type = %s AND object_id = %d AND meta_key = %s",
                        $objectType, $objectId, $metaKey
                    )
                );
                return array_map('maybe_unserialize', $results);
            }
        }
    }

    /**
     * Delete metadata
     */
    public static function delete($objectType, $objectId, $metaKey = '', $metaValue = '') {
        $db = VT_Database::getInstance();
        $table = $db->prefix . 'meta';

        $where = array(
            'object_type' => vt_service('validation.sanitizer')->textField($objectType),
            'object_id' => intval($objectId)
        );

        if (!empty($metaKey)) {
            $where['meta_key'] = vt_service('validation.sanitizer')->textField($metaKey);
        }

        if (!empty($metaValue)) {
            $where['meta_value'] = maybe_serialize($metaValue);
        }

        return $db->delete('meta', $where);
    }
}

/**
 * Simple serialization helpers
 */
function maybe_serialize($data) {
    if (is_array($data) || is_object($data)) {
        return serialize($data);
    }
    return $data;
}

function maybe_unserialize($data) {
    if (is_serialized($data)) {
        return unserialize($data);
    }
    return $data;
}

function is_serialized($data, $strict = true) {
    if (!is_string($data)) {
        return false;
    }
    $data = trim($data);
    if ('N;' === $data) {
        return true;
    }
    if (strlen($data) < 4) {
        return false;
    }
    if (':' !== $data[1]) {
        return false;
    }
    if ($strict) {
        $lastc = substr($data, -1);
        if (';' !== $lastc && '}' !== $lastc) {
            return false;
        }
    } else {
        $semicolon = strpos($data, ';');
        $brace = strpos($data, '}');
        if (false === $semicolon && false === $brace) {
            return false;
        }
        if (false !== $semicolon && $semicolon < 3) {
            return false;
        }
        if (false !== $brace && $brace < 4) {
            return false;
        }
    }
    $token = $data[0];
    switch ($token) {
        case 's':
            if ($strict) {
                if ('"' !== substr($data, -2, 1)) {
                    return false;
                }
            } elseif (false === strpos($data, '"')) {
                return false;
            }
            // No break, fall through.
        case 'a':
        case 'O':
            return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';
            return (bool) preg_match("/^{$token}:[0-9.E+-]+;$end/", $data);
    }
    return false;
}