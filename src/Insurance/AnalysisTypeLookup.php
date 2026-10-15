<?php

declare(strict_types=1);

namespace KSFII\Insurance;

use PDO;
use PDOException;

/**
 * Analysis Type Lookup Class
 *
 * Provides database-driven access to insurance analysis types,
 * replacing hardcoded switch statements in calculation engines.
 *
 * @package KSFII\Insurance
 */
class AnalysisTypeLookup
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all active analysis types
     *
     * @return array List of active analysis types
     */
    public function getAllActiveTypes(): array
    {
        if (!empty($this->cache['active_types'])) {
            return $this->cache['active_types'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, type_code, type_name, description
                FROM insurance_analysis_types
                WHERE is_active = 1
                ORDER BY type_name
            ");
            $stmt->execute();
            $this->cache['active_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->cache['active_types'];
        } catch (PDOException $e) {
            // Log error and return empty array as fallback
            error_log("AnalysisTypeLookup::getAllActiveTypes() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get analysis type by code
     *
     * @param string $typeCode The analysis type code
     * @return array|null Analysis type data or null if not found/inactive
     */
    public function getTypeByCode(string $typeCode): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, type_code, type_name, description
                FROM insurance_analysis_types
                WHERE type_code = ? AND is_active = 1
            ");
            $stmt->execute([$typeCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("AnalysisTypeLookup::getTypeByCode() - Database error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if analysis type is valid and active
     *
     * @param string $typeCode The analysis type code to validate
     * @return bool True if valid and active
     */
    public function isValidType(string $typeCode): bool
    {
        return $this->getTypeByCode($typeCode) !== null;
    }

    /**
     * Get all active type codes
     *
     * @return array Array of active type codes
     */
    public function getTypeCodes(): array
    {
        $types = $this->getAllActiveTypes();
        return array_column($types, 'type_code');
    }

    /**
     * Clear internal cache
     * Call this after making changes to analysis types
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get analysis type name by code
     *
     * @param string $typeCode The analysis type code
     * @return string|null Type name or null if not found
     */
    public function getTypeName(string $typeCode): ?string
    {
        $type = $this->getTypeByCode($typeCode);
        return $type ? $type['type_name'] : null;
    }
}