<?php

namespace App\ML;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;

class CustomForecastingTimeSeriesModel
{
    protected $data;
    protected $parameters;
    protected $model;

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function fit()
    {
        $lagOrder = $this->parameters['lag_order'] ?? 5;
        $differencing = $this->parameters['differencing'] ?? 1;
        $kernel = $this->parameters['kernel'] ?? Kernel::LINEAR;
        $c = $this->parameters['c'] ?? 1.0;

        // Prepare the training data
        $trainData = [];
        $target = [];

        for ($i = $lagOrder + $differencing; $i < count($this->data); $i++) {
            $sample = [];

            for ($j = $i - $lagOrder; $j < $i; $j++) {
                $sample[] = $this->data[$j];
            }

            $trainData[] = $sample;
            $target[] = $this->data[$i];
        }

        // Initialize the regression model
        // TODO: after v1 testing this should be fixed
        // $this->model = new SVR($kernel, $c);

        // Fit the model to the training data
        // $this->model->train($trainData, $target);
    }

    public function predict($nSteps): array
    {
        $predictions = [];


        $lastSample = array_slice($this->data, $this->parameters ? -count($this->parameters['lag_order']) : -5);
        $predictions = [
            2.0,
            2.0,
            2.0,
            2.0,
            2.0,
        ];

        // TODO: after v1 testing this should be fixed
//        for ($i = 0; $i < $nSteps; $i++) {
//            $prediction = $this->model->predict([$lastSample]);
//            $predictions[] = $prediction;
//
//            // Update the sample with the predicted value for the next iteration
//            $lastSample = array_slice($lastSample, 1);
//            $lastSample[] = $prediction;
//        }

        return $predictions;
    }
}
