<?php

declare(strict_types=1);

namespace KSFII\Insurance;

use PDO;
use PDOException;

/**
 * Calculation Parameter Lookup Class
 *
 * Provides database-driven access to insurance calculation parameters,
 * replacing hardcoded multipliers and rates in calculation engines.
 *
 * @package KSFII\Insurance
 */
class CalculationParameterLookup
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get parameter value by name
     *
     * @param string $parameterName The parameter name
     * @return float|null Parameter value or null if not found/inactive
     */
    public function getParameterValue(string $parameterName): ?float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT parameter_value
                FROM insurance_calculation_parameters
                WHERE parameter_name = ? AND is_active = 1
            ");
            $stmt->execute([$parameterName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float) $result['parameter_value'] : null;
        } catch (PDOException $e) {
            error_log("CalculationParameterLookup::getParameterValue() - Database error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get parameter value with fallback
     *
     * @param string $parameterName The parameter name
     * @param float $fallbackValue Fallback value if parameter not found
     * @return float Parameter value or fallback
     */
    public function getParameterValueWithFallback(string $parameterName, float $fallbackValue): float
    {
        $value = $this->getParameterValue($parameterName);
        return $value !== null ? $value : $fallbackValue;
    }

    /**
     * Get all active parameters
     *
     * @return array List of all active parameters
     */
    public function getAllActiveParameters(): array
    {
        if (!empty($this->cache['all_parameters'])) {
            return $this->cache['all_parameters'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, parameter_name, parameter_value, parameter_unit, description, analysis_type
                FROM insurance_calculation_parameters
                WHERE is_active = 1
                ORDER BY parameter_name
            ");
            $stmt->execute();
            $this->cache['all_parameters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->cache['all_parameters'];
        } catch (PDOException $e) {
            error_log("CalculationParameterLookup::getAllActiveParameters() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get parameters by analysis type
     *
     * @param string $analysisType The analysis type
     * @return array List of parameters for the analysis type
     */
    public function getParametersByType(string $analysisType): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, parameter_name, parameter_value, parameter_unit, description
                FROM insurance_calculation_parameters
                WHERE (analysis_type = ? OR analysis_type IS NULL) AND is_active = 1
                ORDER BY parameter_name
            ");
            $stmt->execute([$analysisType]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CalculationParameterLookup::getParametersByType() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update parameter value
     *
     * @param string $parameterName The parameter name
     * @param float $newValue The new value
     * @return bool True if update successful
     */
    public function updateParameterValue(string $parameterName, float $newValue): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE insurance_calculation_parameters
                SET parameter_value = ?, updated_at = CURRENT_TIMESTAMP
                WHERE parameter_name = ? AND is_active = 1
            ");
            $result = $stmt->execute([$newValue, $parameterName]);

            // Clear cache after update
            $this->clearCache();

            return $result && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("CalculationParameterLookup::updateParameterValue() - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add new parameter
     *
     * @param string $parameterName The parameter name
     * @param float $value The parameter value
     * @param string|null $unit The unit of measurement
     * @param string|null $description Parameter description
     * @param string|null $analysisType Specific analysis type (null for global)
     * @return bool True if addition successful
     */
    public function addParameter(
        string $parameterName,
        float $value,
        ?string $unit = null,
        ?string $description = null,
        ?string $analysisType = null
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO insurance_calculation_parameters
                (parameter_name, parameter_value, parameter_unit, description, analysis_type, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $result = $stmt->execute([$parameterName, $value, $unit, $description, $analysisType]);

            // Clear cache after addition
            $this->clearCache();

            return $result;
        } catch (PDOException $e) {
            error_log("CalculationParameterLookup::addParameter() - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear internal cache
     * Call this after making changes to parameters
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}