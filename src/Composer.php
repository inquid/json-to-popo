<?php

/*
 * This file is part of the abgeo/json-to-popo.
 *
 * Copyright (C) 2020 Temuri Takalandze <takalandzet@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ABGEO\POPO;

use ABGEO\POPO\Util\Normalizer;

/**
 * Compose Plain Old PHP Object from JSON content.
 *
 * @author Temuri Takalandze <takalandzet@gmail.com>
 */
class Composer
{
    /**
     * Compose a new object of this given class
     * and fill it with the given JSON content.
     *
     * @param string $json  JSON content to fill the new object.
     * @param string $class Class to create a new object from.
     *
     * @return mixed New filled with JSON content object of $class class.
     *
     * @throws \ReflectionException
     */
    public function composeObject(string $json, string $class)
    {
        $jsonDecoded = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("The JSON content is invalid!");
        }

        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class '$class' not found!");
        }

        $mainObject = new $class();

        foreach (get_object_vars($jsonDecoded) as $property => $value) {
            $this->fillObject(Normalizer::camelize($property), $value, $mainObject);
        }

        return $mainObject;
    }

    /**
     * Recursively fill a given property
     * of a given object with a given value.
     *
     * @param string $property Object property to fill.
     * @param mixed  $value    Value to fill object property with.
     * @param mixed  $object   Object to fill.
     *
     * @throws \ReflectionException
     */
    private function fillObject(string $property, $value, $object)
    {
        $class = get_class($object);
        $propertySetter = 'set' . ucfirst($property);
        if (!method_exists($object, $propertySetter)) {
            throw new \RuntimeException("Class '{$class}' does not have a method '{$propertySetter}'");
        }

        if (is_object($value)) {
            $reflectionProperty = new \ReflectionProperty($class, $property);

            if (!$propertyType = $reflectionProperty->getType()) {
                throw new \RuntimeException(
                    "Type of Property '{$class}::\${$property}' is undefined!"
                );
            }

            $propertyTypeName = $propertyType->getName();

            if ('array' === $propertyTypeName) {
                $_value = [];
                $this->fillArray($_value, $value);
                $value = $_value;
            } else {
                $_object = new $propertyTypeName();
                foreach (get_object_vars($value) as $_property => $_value) {
                    $this->fillObject(Normalizer::camelize($_property), $_value, $_object);
                }
                $value = $_object;
            }
        }

        call_user_func_array([$object, $propertySetter], [$value]);
    }

    /**
     * Recursively fill a given array with a given Std Class.
     *
     * @param array     $array Reference to Array to fill.
     * @param \stdClass $value Std Class Value to fill array with.
     */
    private function fillArray(array &$array, \stdClass $value)
    {
        foreach (get_object_vars($value) as $_key => $_value) {
            if (is_object($_value)) {
                $array[$_key] = [];
                $this->fillArray($array[$_key], $_value);
            } else {
                $array[$_key] = $_value;
            }
        }
    }
}
