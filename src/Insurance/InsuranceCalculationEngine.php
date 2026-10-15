<?php

declare(strict_types=1);

namespace KSFII\Insurance;

use PDO;

/**
 * Insurance Calculation Engine
 * Database-driven insurance calculation engine that replaces hardcoded business logic
 * with configurable database lookups for analysis types, parameters, default values, and economic assumptions.
 *
 * @package KSFII\Insurance
 * @version 1.0.0
 * @author Kevin Fraser
 */
class InsuranceCalculationEngine
{
    private AnalysisTypeLookup $analysisTypeLookup;
    private CalculationParameterLookup $parameterLookup;
    private DefaultValueLookup $defaultValueLookup;
    private EconomicAssumptionLookup $economicAssumptionLookup;

    public function __construct(PDO $pdo)
    {
        $this->analysisTypeLookup = new AnalysisTypeLookup($pdo);
        $this->parameterLookup = new CalculationParameterLookup($pdo);
        $this->defaultValueLookup = new DefaultValueLookup($pdo);
        $this->economicAssumptionLookup = new EconomicAssumptionLookup($pdo);
    }

    /**
     * Calculate insurance needs based on analysis type
     *
     * @param array $data Input data for calculation
     * @return array Calculation results
     * @throws \InvalidArgumentException If analysis type is invalid or validation fails
     */
    public function calculate(array $data): array
    {
        // Validate input data
        $validationErrors = $this->validateData($data);
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validationErrors));
        }

        $analysisType = $data['analysis_type'] ?? 'life_insurance';

        // Validate analysis type using database lookup
        if (!$this->analysisTypeLookup->isValidType($analysisType)) {
            throw new \InvalidArgumentException("Invalid analysis type: {$analysisType}");
        }

        switch ($analysisType) {
            case 'life_insurance':
                return $this->calculateLifeInsurance($data);
            case 'critical_illness':
                return $this->calculateCriticalIllness($data);
            case 'combined':
                return $this->calculateCombined($data);
            default:
                throw new \InvalidArgumentException("Unsupported analysis type: {$analysisType}");
        }
    }

    /**
     * Calculate life insurance needs using database-driven parameters
     *
     * @param array $data Input data
     * @return array Calculation results
     */
    private function calculateLifeInsurance(array $data): array
    {
        $clientIncome = (float) ($data['client_income'] ?? 0);
        $spouseIncome = (float) ($data['spouse_income'] ?? 0);
        $debtsTotal = (float) ($data['debts_total'] ?? 0);
        $existingCoverage = (float) ($data['existing_coverage'] ?? 0);

        // Get default values from database
        $clientProfile = $data['client_profile'] ?? 'standard';
        $region = $data['region'] ?? 'ON';

        $finalExpenses = (float) ($data['final_expenses'] ?? 
            $this->defaultValueLookup->getDefaultValue('final_expenses', $clientProfile, $region) ?? 15000);
        $emergencyFund = (float) ($data['emergency_fund'] ?? 
            $this->defaultValueLookup->getDefaultValue('emergency_fund', $clientProfile, $region) ?? 25000);
        $educationFund = (float) ($data['education_fund'] ?? 
            $this->defaultValueLookup->getDefaultValue('education_fund', $clientProfile, $region) ?? 100000);

        $retirementIncomeNeeded = (float) ($data['retirement_income_needed'] ?? 40000);

        // Get economic assumptions from database
        $inflationRate = $this->economicAssumptionLookup->getAssumptionWithFallback('inflation_rate', 2.5) / 100;
        $investmentReturn = $this->economicAssumptionLookup->getAssumptionWithFallback('investment_return', 5.0) / 100;

        $totalIncome = $clientIncome + $spouseIncome;

        // Get calculation parameters from database
        $incomeReplacementYears = $this->parameterLookup->getParameterValue('income_replacement_years') ?? 10;
        $retirementIncomeYears = $this->parameterLookup->getParameterValue('retirement_income_years') ?? 20;
        $premiumRatePerThousand = $this->parameterLookup->getParameterValue('premium_rate_per_thousand') ?? 0.0008;

        // Calculate needs-based coverage
        $incomeReplacement = $totalIncome * $incomeReplacementYears;
        $debtCoverage = $debtsTotal;
        $finalExpensesCoverage = $finalExpenses;
        $emergencyFundCoverage = $emergencyFund;
        $educationFundCoverage = $educationFund;
        $retirementCoverage = $retirementIncomeNeeded * $retirementIncomeYears;

        $totalCoverageNeeded = $incomeReplacement + $debtCoverage + $finalExpensesCoverage +
                               $emergencyFundCoverage + $educationFundCoverage + $retirementCoverage;

        // Adjust for existing coverage
        $additionalCoverageNeeded = max(0, $totalCoverageNeeded - $existingCoverage);

        // Estimate monthly premium using database parameter
        $monthlyPremium = $additionalCoverageNeeded * $premiumRatePerThousand;

        return [
            'life_insurance_needed' => $additionalCoverageNeeded,
            'critical_illness_coverage' => 0,
            'total_coverage_recommended' => $additionalCoverageNeeded,
            'monthly_premium_estimate' => $monthlyPremium,
            'breakdown' => [
                'income_replacement' => $incomeReplacement,
                'debt_coverage' => $debtCoverage,
                'final_expenses' => $finalExpensesCoverage,
                'emergency_fund' => $emergencyFundCoverage,
                'education_fund' => $educationFundCoverage,
                'retirement_income' => $retirementCoverage,
                'existing_coverage' => $existingCoverage,
            ],
            'methodology' => 'needs_approach',
            'analysis_type' => 'life_insurance',
            'parameters_used' => [
                'income_replacement_years' => $incomeReplacementYears,
                'retirement_income_years' => $retirementIncomeYears,
                'premium_rate_per_thousand' => $premiumRatePerThousand,
                'inflation_rate' => $inflationRate * 100,
                'investment_return' => $investmentReturn * 100,
            ],
        ];
    }

    /**
     * Calculate critical illness coverage using database-driven parameters
     *
     * @param array $data Input data
     * @return array Calculation results
     */
    private function calculateCriticalIllness(array $data): array
    {
        $clientIncome = (float) ($data['client_income'] ?? 0);
        $spouseIncome = (float) ($data['spouse_income'] ?? 0);
        $criticalIllnessCoverage = (float) ($data['critical_illness_coverage'] ?? 0);
        $disabilityIncomeReplacement = (float) ($data['disability_income_replacement'] ?? 60) / 100;

        $totalIncome = $clientIncome + $spouseIncome;

        // Get calculation parameters from database
        $ciCoverageYears = $this->parameterLookup->getParameterValue('critical_illness_coverage_years') ?? 3;
        $ciPremiumRatePerThousand = $this->parameterLookup->getParameterValue('ci_premium_rate_per_thousand') ?? 0.0012;

        // Critical illness coverage calculation
        $recommendedCiCoverage = $totalIncome * $ciCoverageYears;
        $disabilityCoverage = $totalIncome * $disabilityIncomeReplacement;
        $additionalCiCoverage = max(0, $recommendedCiCoverage - $criticalIllnessCoverage);

        // Estimate monthly premium using database parameter
        $monthlyPremium = $additionalCiCoverage * $ciPremiumRatePerThousand;

        return [
            'life_insurance_needed' => 0,
            'critical_illness_coverage' => $additionalCiCoverage,
            'total_coverage_recommended' => $additionalCiCoverage,
            'monthly_premium_estimate' => $monthlyPremium,
            'breakdown' => [
                'recommended_ci_coverage' => $recommendedCiCoverage,
                'existing_ci_coverage' => $criticalIllnessCoverage,
                'disability_income_replacement' => $disabilityCoverage,
            ],
            'methodology' => 'income_replacement',
            'analysis_type' => 'critical_illness',
            'parameters_used' => [
                'critical_illness_coverage_years' => $ciCoverageYears,
                'ci_premium_rate_per_thousand' => $ciPremiumRatePerThousand,
            ],
        ];
    }

    /**
     * Calculate combined life insurance and critical illness coverage
     *
     * @param array $data Input data
     * @return array Combined calculation results
     */
    private function calculateCombined(array $data): array
    {
        $lifeResults = $this->calculateLifeInsurance($data);
        $ciResults = $this->calculateCriticalIllness($data);

        return [
            'life_insurance_needed' => $lifeResults['life_insurance_needed'],
            'critical_illness_coverage' => $ciResults['critical_illness_coverage'],
            'total_coverage_recommended' => $lifeResults['total_coverage_recommended'] + $ciResults['total_coverage_recommended'],
            'monthly_premium_estimate' => $lifeResults['monthly_premium_estimate'] + $ciResults['monthly_premium_estimate'],
            'breakdown' => array_merge($lifeResults['breakdown'], $ciResults['breakdown']),
            'methodology' => 'combined',
            'analysis_type' => 'combined',
            'parameters_used' => array_merge(
                $lifeResults['parameters_used'] ?? [],
                $ciResults['parameters_used'] ?? []
            ),
        ];
    }

    /**
     * Validate calculation data
     *
     * @param array $data Input data to validate
     * @return array List of validation errors
     */
    public function validateData(array $data): array
    {
        $errors = [];

        // Required fields validation
        $requiredFields = ['client_age', 'client_income'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "{$field} is required";
            }
        }

        // Numeric validation
        $numericFields = [
            'client_age', 'client_income', 'spouse_age', 'spouse_income',
            'debts_total', 'assets_total', 'existing_coverage',
            'critical_illness_coverage', 'final_expenses', 'emergency_fund',
            'education_fund', 'retirement_income_needed', 'inflation_rate', 'investment_return'
        ];

        foreach ($numericFields as $field) {
            if (!empty($data[$field]) && !is_numeric($data[$field])) {
                $errors[] = "{$field} must be a number";
            }
        }

        // Range validation
        if (!empty($data['client_age']) && ($data['client_age'] < 18 || $data['client_age'] > 100)) {
            $errors[] = 'Client age must be between 18 and 100';
        }

        // Validate analysis type
        if (!empty($data['analysis_type']) && !$this->analysisTypeLookup->isValidType($data['analysis_type'])) {
            $errors[] = 'Invalid analysis type';
        }

        return $errors;
    }

    /**
     * Get available analysis types
     *
     * @return array List of active analysis types
     */
    public function getAvailableAnalysisTypes(): array
    {
        return $this->analysisTypeLookup->getAllActiveTypes();
    }

    /**
     * Get calculation parameters for a specific analysis type
     *
     * @param string $analysisType The analysis type
     * @return array Parameters for the analysis type
     */
    public function getCalculationParameters(string $analysisType): array
    {
        return $this->parameterLookup->getParametersByType($analysisType);
    }
}