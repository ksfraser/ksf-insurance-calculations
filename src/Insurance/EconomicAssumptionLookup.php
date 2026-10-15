<?php

declare(strict_types=1);

namespace KSFII\Insurance;

use PDO;
use PDOException;
use DateTime;

/**
 * Economic Assumption Lookup Class
 *
 * Provides database-driven access to economic assumptions,
 * replacing hardcoded rates in calculation engines.
 *
 * @package KSFII\Insurance
 */
class EconomicAssumptionLookup
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get current assumption value by name
     * Returns the most recent effective assumption
     *
     * @param string $assumptionName The assumption name
     * @return float|null Current assumption value or null if not found
     */
    public function getCurrentAssumption(string $assumptionName): ?float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT assumption_rate
                FROM economic_assumptions
                WHERE assumption_name = ? AND is_active = 1
                AND effective_date <= date('now')
                AND (end_date IS NULL OR end_date >= date('now'))
                ORDER BY effective_date DESC
                LIMIT 1
            ");
            $stmt->execute([$assumptionName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float) $result['assumption_rate'] : null;
        } catch (PDOException $e) {
            error_log("EconomicAssumptionLookup::getCurrentAssumption() - Database error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get assumption value by date
     *
     * @param string $assumptionName The assumption name
     * @param string $date The date in YYYY-MM-DD format
     * @return float|null Assumption value for the date or null if not found
     */
    public function getAssumptionByDate(string $assumptionName, string $date): ?float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT assumption_rate
                FROM economic_assumptions
                WHERE assumption_name = ? AND is_active = 1
                AND effective_date <= ?
                AND (end_date IS NULL OR end_date >= ?)
                ORDER BY effective_date DESC
                LIMIT 1
            ");
            $stmt->execute([$assumptionName, $date, $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (float) $result['assumption_rate'] : null;
        } catch (PDOException $e) {
            error_log("EconomicAssumptionLookup::getAssumptionByDate() - Database error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get assumption value with fallback
     *
     * @param string $assumptionName The assumption name
     * @param float $fallbackValue Fallback value if not found
     * @return float Assumption value or fallback
     */
    public function getAssumptionWithFallback(string $assumptionName, float $fallbackValue): float
    {
        $value = $this->getCurrentAssumption($assumptionName);
        return $value !== null ? $value : $fallbackValue;
    }

    /**
     * Get assumption history
     *
     * @param string $assumptionName The assumption name
     * @return array List of historical assumption values
     */
    public function getAssumptionHistory(string $assumptionName): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT assumption_rate, effective_date, end_date, source, description
                FROM economic_assumptions
                WHERE assumption_name = ? AND is_active = 1
                ORDER BY effective_date DESC
            ");
            $stmt->execute([$assumptionName]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("EconomicAssumptionLookup::getAssumptionHistory() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all current assumptions
     *
     * @return array List of all current active assumptions
     */
    public function getAllCurrentAssumptions(): array
    {
        if (!empty($this->cache['current_assumptions'])) {
            return $this->cache['current_assumptions'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT ea.assumption_name, ea.assumption_rate, ea.effective_date, ea.source, ea.description
                FROM economic_assumptions ea
                INNER JOIN (
                    SELECT assumption_name, MAX(effective_date) as max_date
                    FROM economic_assumptions
                    WHERE is_active = 1 AND effective_date <= date('now')
                    AND (end_date IS NULL OR end_date >= date('now'))
                    GROUP BY assumption_name
                ) latest ON ea.assumption_name = latest.assumption_name
                AND ea.effective_date = latest.max_date
                WHERE ea.is_active = 1
                ORDER BY ea.assumption_name
            ");
            $stmt->execute();
            $this->cache['current_assumptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->cache['current_assumptions'];
        } catch (PDOException $e) {
            error_log("EconomicAssumptionLookup::getAllCurrentAssumptions() - Database error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update assumption value
     *
     * @param string $assumptionName The assumption name
     * @param float $newRate The new rate
     * @param string $effectiveDate Effective date (YYYY-MM-DD)
     * @return bool True if update successful
     */
    public function updateAssumption(string $assumptionName, float $newRate, string $effectiveDate): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE economic_assumptions
                SET assumption_rate = ?, updated_at = CURRENT_TIMESTAMP
                WHERE assumption_name = ? AND effective_date = ? AND is_active = 1
            ");
            $result = $stmt->execute([$newRate, $assumptionName, $effectiveDate]);

            // Clear cache after update
            $this->clearCache();

            return $result && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("EconomicAssumptionLookup::updateAssumption() - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add new assumption
     *
     * @param string $assumptionName The assumption name
     * @param float $rate The assumption rate
     * @param string|null $description Description
     * @param string $effectiveDate Effective date (YYYY-MM-DD)
     * @param string|null $endDate End date (YYYY-MM-DD) or null
     * @param string|null $source Source of the assumption
     * @return bool True if addition successful
     */
    public function addAssumption(
        string $assumptionName,
        float $rate,
        ?string $description = null,
        string $effectiveDate = 'current_date',
        ?string $endDate = null,
        ?string $source = null
    ): bool {
        try {
            if ($effectiveDate === 'current_date') {
                $effectiveDate = date('Y-m-d');
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO economic_assumptions
                (assumption_name, assumption_rate, description, effective_date, end_date, source, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $result = $stmt->execute([$assumptionName, $rate, $description, $effectiveDate, $endDate, $source]);

            // Clear cache after addition
            $this->clearCache();

            return $result;
        } catch (PDOException $e) {
            error_log("EconomicAssumptionLookup::addAssumption() - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear internal cache
     * Call this after making changes to assumptions
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}