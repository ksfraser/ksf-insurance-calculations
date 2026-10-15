<?php

declare(strict_types=1);

namespace KSFII\Insurance;

use PDO;
use PDOException;

/**
 * Default Value Lookup Class
 *
 * Provides database-driven access to insurance default values,
 * replacing hardcoded financial defaults in calculation engines.
 *
 * @package KSFII\Insurance
 */
class DefaultValueLookup
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get default value by name, profile, and region
     *
     * @param string $valueName The value name (e.g., 'final_expenses')
     * @param string $clientProfile The client profile (default: 'standard')
     * @param string|null $region The region code (default: null for global)
     * @return float|null Default value or null if not found
     */
    public function getDefaultValue(string $valueName, string $clientProfile = 'standard', ?string $region = null): ?float
    {
        try {
            // Try specific region first, then fallback to global (region = null)
            $stmt = $this->pdo->prepare("
                SELECT value_amount
                FROM insurance_default_values
                WHERE value_name = ? AND client_profile = ? AND (region = ? OR region IS NULL) AND is_active = 1
                ORDER BY region IS NULL ASC  -- Prefer specific region over global
                LIMIT 1
            ");
            $stmt->execute([$valueName, $clientProfile, $region]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float) $result['value_amount'] : null;
        } catch (PDOException $e) {
            error_log("DefaultValueLookup::getDefaultValue() - Database error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get default value with fallback
     *
     * @param string $valueName The value name
     * @param float $fallbackValue Fallback value if not found
     * @param string $clientProfile The client profile
     * @param string|null $region The region code
     * @return float Default value or fallback
     */
    public function getDefaultValueWithFallback(
        string $valueName,
        float $fallbackValue,
        string $clientProfile = 'standard',
        ?string $region = null
    ): float {
        $value = $this->getDefaultValue($valueName, $clientProfile, $region);
        return $value !== null ? $value : $fallbackValue;
    }

    /**
     * Get all defaults by profile and region
     *
     * @param string $clientProfile The client profile
     * @param string|null $region The region code
     * @return array List of default values
     */
    public function getDefaultsByProfile(string $clientProfile = 'standard', ?string $region = null): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT value_name, value_amount, value_unit, description
                FROM insurance_default_values
                WHERE client_profile = ? AND (region = ? OR region IS NULL) AND is_active = 1
                ORDER BY value_name
            ");
            $stmt->execute([$clientProfile, $region]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DefaultValueLookup::getDefaultsByProfile() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active default values
     *
     * @return array List of all active default values
     */
    public function getAllDefaults(): array
    {
        if (!empty($this->cache['all_defaults'])) {
            return $this->cache['all_defaults'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, value_name, value_amount, value_unit, description, client_profile, region
                FROM insurance_default_values
                WHERE is_active = 1
                ORDER BY value_name, client_profile, region
            ");
            $stmt->execute();
            $this->cache['all_defaults'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->cache['all_defaults'];
        } catch (PDOException $e) {
            error_log("DefaultValueLookup::getAllDefaults() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update default value
     *
     * @param string $valueName The value name
     * @param float $newAmount The new amount
     * @param string $clientProfile The client profile
     * @param string|null $region The region code
     * @return bool True if update successful
     */
    public function updateDefaultValue(
        string $valueName,
        float $newAmount,
        string $clientProfile = 'standard',
        ?string $region = null
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE insurance_default_values
                SET value_amount = ?, updated_at = CURRENT_TIMESTAMP
                WHERE value_name = ? AND client_profile = ? AND (region = ? OR (region IS NULL AND ? IS NULL)) AND is_active = 1
            ");
            $result = $stmt->execute([$newAmount, $valueName, $clientProfile, $region, $region]);

            // Clear cache after update
            $this->clearCache();

            return $result && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("DefaultValueLookup::updateDefaultValue() - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add new default value
     *
     * @param string $valueName The value name
     * @param float $amount The default amount
     * @param string $unit The unit (default: 'CAD')
     * @param string|null $description Description
     * @param string $clientProfile The client profile
     * @param string|null $region The region code
     * @return bool True if addition successful
     */
    public function addDefaultValue(
        string $valueName,
        float $amount,
        string $unit = 'CAD',
        ?string $description = null,
        string $clientProfile = 'standard',
        ?string $region = null
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO insurance_default_values
                (value_name, value_amount, value_unit, description, client_profile, region, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $result = $stmt->execute([$valueName, $amount, $unit, $description, $clientProfile, $region]);

            // Clear cache after addition
            $this->clearCache();

            return $result;
        } catch (PDOException $e) {
            error_log("DefaultValueLookup::addDefaultValue() - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear internal cache
     * Call this after making changes to default values
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}