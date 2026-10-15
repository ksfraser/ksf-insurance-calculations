<?php

declare(strict_types=1);

namespace KSFII\FrontAccounting\FNA;

/**
 * FNA Data Model
 * Data Transfer Object for Financial Needs Analysis
 *
 * @package KSFII\FrontAccounting\FNA
 */
class FNAData
{
    public function __construct(
        public string $debtorNo,
        public int $version,
        public string $analysisType,
        public string $methodology,
        public int $clientAge,
        public string $clientHealth,
        public int $retirementAge,
        public int $lifeExpectancy,
        public float $annualIncome,
        public float $spouseAnnualIncome,
        public float $pensionIncome,
        public float $otherIncome,
        public float $familyIncomeNeed,
        public int $incomeReplacementYears,
        public float $childcareAnnual,
        public float $educationFunding,
        public float $mortgageBalance,
        public float $otherDebt,
        public float $finalExpenses,
        public float $savings,
        public float $rrspBalance,
        public float $tfsaBalance,
        public float $nonRegInvestments,
        public float $realEstateValue,
        public float $businessValue,
        public float $otherAssets,
        public float $lifeInsurance,
        public float $criticalIllness,
        public float $deathBenefits,
        public float $inflationRate,
        public float $investmentReturn,
        public float $incomeTaxRate,
        public string $incomeSource,
        public string $expenseSource,
        public string $assetSource,
    ) {}

    public function toArray(): array
    {
        return [
            'debtorNo' => $this->debtorNo,
            'version' => $this->version,
            'analysisType' => $this->analysisType,
            'methodology' => $this->methodology,
            'clientAge' => $this->clientAge,
            'clientHealth' => $this->clientHealth,
            'retirementAge' => $this->retirementAge,
            'lifeExpectancy' => $this->lifeExpectancy,
            'annualIncome' => $this->annualIncome,
            'spouseAnnualIncome' => $this->spouseAnnualIncome,
            'pensionIncome' => $this->pensionIncome,
            'otherIncome' => $this->otherIncome,
            'familyIncomeNeed' => $this->familyIncomeNeed,
            'incomeReplacementYears' => $this->incomeReplacementYears,
            'childcareAnnual' => $this->childcareAnnual,
            'educationFunding' => $this->educationFunding,
            'mortgageBalance' => $this->mortgageBalance,
            'otherDebt' => $this->otherDebt,
            'finalExpenses' => $this->finalExpenses,
            'savings' => $this->savings,
            'rrspBalance' => $this->rrspBalance,
            'tfsaBalance' => $this->tfsaBalance,
            'nonRegInvestments' => $this->nonRegInvestments,
            'realEstateValue' => $this->realEstateValue,
            'businessValue' => $this->businessValue,
            'otherAssets' => $this->otherAssets,
            'lifeInsurance' => $this->lifeInsurance,
            'criticalIllness' => $this->criticalIllness,
            'deathBenefits' => $this->deathBenefits,
            'inflationRate' => $this->inflationRate,
            'investmentReturn' => $this->investmentReturn,
            'incomeTaxRate' => $this->incomeTaxRate,
            'incomeSource' => $this->incomeSource,
            'expenseSource' => $this->expenseSource,
            'assetSource' => $this->assetSource,
        ];
    }
}