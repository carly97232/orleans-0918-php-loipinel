<?php

namespace App\Service;

use App\Entity\Finance;
use App\Service\DataPinelJson;
use App\Entity\RealEstateProperty;
use App\Repository\VariableRepository;

class TaxBenefit
{
    /**
     * @var RealEstateProperty
     */
    private $realEstate;

    /**
     * Durée de la location en année
     * @var int
     */
    private $rentalPeriod;

    /**
     * Base fiscale
     * @var float
     */
    private $taxBase;

    private $variable;

    private $variableRepository;

    public function __construct(VariableRepository $variablesRepository)
    {
        $this->variableRepository = $variablesRepository;
        $this->initializeVariableFromDB();
    }

    /**
     * récupère la seule ligne de données de la table variable
     */
    private function initializeVariableFromDB()
    {
        $this->variable = $this->variableRepository->findOneBy([]);
    }

    private function ratesByDuration()
    {
        return $tableOfRatesByDuration = [
            6 => $this->getVariable()->getRateFor6years(),
            9 => $this->getVariable()->getRateFor9years(),
            12 => $this->getVariable()->getRateFor12years(),
        ];
    }

    /**
     * Calcule la base fiscale du bien
     * @return float
     */
    public function calculateTaxBase() : float
    {
        $meterSquarePrice = $this->getRealEstate()->getPurchasePrice() / $this->getRealEstate()->getSurfaceArea();
        if ($meterSquarePrice > $this->getVariable()->getMaximumPricePerSquareMeter()) {
            $meterSquarePrice = $this->getVariable()->getMaximumPricePerSquareMeter();
        }
        return $meterSquarePrice * $this->realEstate->getSurfaceArea();
    }

    /**
     * Calcule l'avantage fiscal en se basant sur la base fiscale et la durée
     * @return float
     */
    public function calculateTaxBenefit() : float
    {
        $taxBenefit = 0;

        $this->setTaxBase($this->calculateTaxBase());

        if ($this->taxBase > $this->getVariable()->getMaximumTaxBase()) {
            $taxBase = $this->getVariable()->getMaximumTaxBase();
        } else {
            $taxBase = $this->taxBase;
        }
        $datesOfRatesByDuration = array_keys($this->ratesByDuration());
        if (array_key_exists($this->getRentalPeriod(), $this->ratesByDuration())) {
            $taxBenefit = $taxBase * $this->ratesByDuration()[$this->getRentalPeriod()];
        } else {
            throw new \LogicException("Only " . implode(", ", $datesOfRatesByDuration) . " accepted.");
        }

        return $taxBenefit;
    }

    /**
     * Calcule l'avantage fiscal annuel en se basant sur l'avantage fiscal total et la durée du plan
     * @param \App\Service\DataPinelJson $dataPinelJson
     * @param Finance $finance
     * @return array
     */
    public function taxBenefitByYear(DataPinelJson $dataPinelJson, Finance $finance) : array
    {
        $this->setTaxBase($this->calculateTaxBase());

        if ($this->taxBase > $this->getVariable()->getMaximumTaxBase()) {
            $taxBase = $this->getVariable()->getMaximumTaxBase();
        } else {
            $taxBase = $this->taxBase;
        }
        $acquisitionDate = date_format($finance->getAcquisitionDate(), 'Y-m-d H:i:s');
        if ($dataPinelJson->getPinelArea($acquisitionDate, $finance->getCity()) != "C") {
            $taxBenefitByYear = [];

            $totalRate = $this->ratesByDuration()[$this->getRentalPeriod()];
            $ratePerYear = ($totalRate * 2) / 21;
            $taxBenefit = $taxBase * $this->ratesByDuration()[$this->getRentalPeriod()];
            if (($this->getRentalPeriod()) <= 9) {
                for ($i = 1; $i <= ($this->getRentalPeriod()); $i++) {
                    $taxBenefitByYear[] = $taxBenefit / $this->getRentalPeriod();
                }
            }
            if (($this->getRentalPeriod()) == 12) {
                for ($i = 1; $i <= 9; $i++) {
                    $taxBenefitByYear[] = $taxBase * $ratePerYear;
                }
                for ($i = 0; $i < 3; $i++) {
                    $taxBenefitByYear[] = $taxBase * ($ratePerYear / 2);
                }
            }
            for ($i = 0; $i < 3; $i++) {
                $taxBenefitByYear[] = 0;
            }
            return $taxBenefitByYear;
        } else {
            return $taxBenefitByYear = [];
        }
    }

    /**
     * @return int
     */
    public function getRentalPeriod(): int
    {
        return $this->rentalPeriod;
    }

    /**
     * @param int $rentalPeriod
     * @return TaxBenefit
     */
    public function setRentalPeriod(int $rentalPeriod): TaxBenefit
    {
        $this->rentalPeriod = $rentalPeriod;
        return $this;
    }

    /**
     * @return RealEstateProperty
     */
    public function getRealEstate(): RealEstateProperty
    {
        return $this->realEstate;
    }

    /*
     * @param RealEstateProperty $realEstate
     * @return TaxBenefit
     */
    public function setRealEstate(RealEstateProperty $realEstate): TaxBenefit
    {
        $this->realEstate = $realEstate;
        return $this;
    }

    /**
     * @return float
     */
    public function getTaxBase(): float
    {
        return $this->taxBase;
    }

    /**
     * @param float $taxBase
     * @return TaxBenefit
     */
    public function setTaxBase(float $taxBase): TaxBenefit
    {
        $this->taxBase = $taxBase;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getVariable()
    {
        return $this->variable;
    }

    /**
     * @param mixed $variable
     */
    public function setVariable($variable): void
    {
        $this->variable = $variable;
    }
}
