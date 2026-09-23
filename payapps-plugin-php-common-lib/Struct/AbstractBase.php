<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

abstract class AbstractBase
{
    /**
     * @var array
     */
    private $rawData;

    /**
     * @var string
     */
    private $classType;

    /**
     * @param array $dataArray
     */
    public function __construct(array $dataArray = [])
    {
        if (!empty($dataArray)) {
            $this->setFromArray($dataArray);
            $this->setRawData($dataArray);
            $this->setClassType(static::class);
        }
    }

    /**
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData ?? [];
    }

    /**
     * @param array $rawData
     * @return $this
     */
    public function setRawData(array $rawData): self
    {
        $this->rawData = $rawData;
        return $this;
    }

    /**
     * @return string
     */
    public function getClassType(): string
    {
        return $this->classType ?? '';
    }

    /**
     * @param string $rawType
     * @return $this
     */
    public function setClassType(string $rawType): self
    {
        $this->classType = $rawType;
        return $this;
    }

    /**
     * @param array $dataArray
     *
     * @return void
     */
    public function setFromArray(array $dataArray)
    {
        foreach ($dataArray as $fieldName => $fieldValue) {
            $propertyName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName))));
            $methodName = 'set' . ucfirst($propertyName);

            if (method_exists($this, $methodName)) {
                $this->{$methodName}($fieldValue);
            }
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $property => $value) {
            if (is_object($value) && method_exists($value, 'toArray')) {
                $result[$property] = $value->toArray();
            } else {
                $result[$property] = $value;
            }
        }
        return $result;
    }
}
