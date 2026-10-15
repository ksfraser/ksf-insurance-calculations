<?php

declare(strict_types=1);

namespace KSFII\FrontAccounting\Services;

use KSFII\FrontAccounting\FNA\FNAData;
use KSFII\FrontAccounting\FNA\FNADataServiceInterface;
use Ksfraser\Insurance\InsuranceCalculationEngine;
use Ksfraser\ModulesCommon\CalculationContext;

/**
 * FNA Calculation Service
 * Integrates with InsuranceNeedsCalculator to perform FNA calculations.
 * Follows SRP: single responsibility for FNA calculation orchestration.
 *
 * @package KSFII\FrontAccounting\Services
 */
class FNACalculationService
{
    private InsuranceCalculationEngine $insuranceCalculator;
    private BudgetCalculatorService $budgetCalculator;

    public function __construct(
        InsuranceCalculationEngine $insuranceCalculator,
        BudgetCalculatorService $budgetCalculator
    ) {
        $this->insuranceCalculator = $insuranceCalculator;
        $this->budgetCalculator = $budgetCalculator;
    }

    /** Run full FNA calculation from FNAData. */
    public function calculate(FNAData $fna): array
    {
        $context = $this->fnaToContext($fna);
        $result = $this->insuranceCalculator->calculate($context);

        if (!$result->isSuccessful) {
            return [
                'success' => false,
                'errors' => $result->errors,
            ];
        }

        return [
            'success' => true,
            'data' => $this->transformResults($result->results, $fna),
        ];
    }

    /** Calculate life insurance need only. */
    public function calculateLifeInsurance(FNAData $fna): array
    {
        $context = $this->fnaToContext($fna);
        $context->setParameter('analysis_type', 'life_insurance');

        $result = $this->insuranceCalculator->calculate($context);

        if (!$result->isSuccessful) {
            return [
                'success' => false,
                'errors' => $result->errors,
            ];
        }

        return [
            'success' => true,
            'life_insurance_need' => $result->results['calculated_need'] ?? 0,
            'recommended_coverage' => $result->results['recommended_coverage'] ?? 0,
            'methodology' => $result->results['methodology'] ?? 'needs_approach',
            'breakdown' => $this->formatLifeInsuranceBreakdown($result->results),
        ];
    }

    /** Calculate critical illness need only. */
    public function calculateCriticalIllness(FNAData $fna): array
    {
        $context = $this->fnaToContext($fna);
        $context->setParameter('analysis_type', 'critical_illness');

        $result = $this->insuranceCalculator->calculate($context);

        if (!$result->isSuccessful) {
            return [
                'success' => false,
                'errors' => $result->errors,
            ];
        }

        return [
            'success' => true,
            'critical_illness_need' => $result->results['calculated_need'] ?? 0,
            'recommended_coverage' => $result->results['recommended_coverage'] ?? 0,
            'breakdown' => $this->formatCIBreakdown($result->results),
        ];
    }

    /** Get gap analysis (need - existing coverage). */
    public function getGapAnalysis(FNAData $fna): array
    {
        $lifeResult = $this->calculateLifeInsurance($fna);
        $ciResult = $this->calculateCriticalIllness($fna);

        $totalLifeNeed = $lifeResult['life_insurance_need'] ?? 0;
        $totalCINeed = $ciResult['critical_illness_need'] ?? 0;

        $lifeCoverage = $fna->lifeInsurance ?? 0;
        $ciCoverage = $fna->criticalIllness ?? 0;

        return [
            'life_insurance' => [
                'need' => $totalLifeNeed,
                'existing' => $lifeCoverage,
                'gap' => max(0, $totalLifeNeed - $lifeCoverage),
            ],
            'critical_illness' => [
                'need' => $totalCINeed,
                'existing' => $ciCoverage,
                'gap' => max(0, $totalCINeed - $ciCoverage),
            ],
            'total_gap' => max(0, $totalLifeNeed - $lifeCoverage) + max(0, $totalCINeed - $ciCoverage),
        ];
    }

    private function fnaToContext(FNAData $fna): CalculationContext
    {
        $context = new CalculationContext(
            clientId: $fna->debtorNo,
            calculationType: 'insurance_needs_analysis'
        );

        $context->setParameter('analysis_type', $fna->analysisType);
        $context->setParameter('methodology', $fna->methodology);
        $context->setParameter('annual_income', $fna->annualIncome);
        $context->setParameter('spouse_annual_income', $fna->spouseAnnualIncome);
        $context->setParameter('survivor_pension_income', $fna->pensionIncome);
        $context->setParameter('family_income_need', $fna->familyIncomeNeed);
        $context->setParameter('income_replacement_years', $fna->incomeReplacementYears);

        $context->setParameter('mortgage_balance', $fna->mortgageBalance);
        $context->setParameter('other_debt', $fna->otherDebt);
        $context->setParameter('final_expenses', $fna->finalExpenses);

        $context->setParameter('emergency_fund_needed', 0);
        $context->setParameter('childcare_costs', $fna->childcareAnnual);
        $context->setParameter('education_funding', $fna->educationFunding);

        $context->setParameter('savings_balance', $fna->savings);
        $context->setParameter('non_registered_investments', $fna->nonRegInvestments);
        $context->setParameter('registered_investments', $fna->rrspBalance);
        $context->setParameter('tfsa_balance', $fna->tfsaBalance);
        $context->setParameter('real_estate_value', $fna->realEstateValue);
        $context->setParameter('business_assets_value', $fna->businessValue);

        $context->setParameter('current_life_insurance', $fna->lifeInsurance);
        $context->setParameter('other_death_benefits', $fna->deathBenefits);

        $context->setParameter('expected_rate_of_return', $fna->investmentReturn);
        $context->setParameter('safety_margin', 0);

        return $context;
    }

    private function transformResults(array $results, FNAData $fna): array
    {
        return [
            'life_insurance_need' => $results['calculated_need'] ?? 0,
            'recommended_coverage' => $results['recommended_coverage'] ?? 0,
            'methodology' => $results['methodology'] ?? 'needs_approach',
            'breakdown' => [
                'debt_and_final_expenses' => $results['debt_final_expenses'] ?? 0,
                'annual_income_needs' => $results['annual_income_needs'] ?? 0,
                'total_assets_coverage' => $results['total_assets_coverage'] ?? 0,
            ],
            'client_data' => $fna->toArray(),
        ];
    }

    private function formatLifeInsuranceBreakdown(array $results): array
    {
        return [
            'Debts & Final Expenses' => $results['debt_final_expenses'] ?? 0,
            'Annual Income Needs' => $results['annual_income_needs'] ?? 0,
            'Assets & Existing Coverage' => $results['total_assets_coverage'] ?? 0,
            'Calculated Need' => $results['calculated_need'] ?? 0,
            'Recommended Coverage' => $results['recommended_coverage'] ?? 0,
        ];
    }

    private function formatCIBreakdown(array $results): array
    {
        return [
            'Healthcare Costs' => $results['healthcare_costs'] ?? 0,
            'Income Supplementation' => $results['income_supplementation'] ?? 0,
            'Lump Sum Costs' => $results['lump_sum_costs'] ?? 0,
            'Calculated Need' => $results['calculated_need'] ?? 0,
            'Recommended Coverage' => $results['recommended_coverage'] ?? 0,
        ];
    }
}