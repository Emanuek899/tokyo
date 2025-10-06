<?php
/**
 * General Validator 
 */
class Validator{
    public static array $errors = [];
    
    /**
     * Format the data to be validated with the respective method, each one
     * @param array $data Data to be validated format ['nameData' => 'value']
     * @param array $Rules Rules to be followed to format the method for 
     *                     analysis, format ['nameData' => 'rule1|rule2']
     * @return array $errors All the errors with of each data parameter
     */
    public static function validate(array $data, array $rules){
        self::$errors = [];
        foreach($rules as $parameter => $rules){
            $r = explode('|', $rules);
            foreach($r as $rule){

                if(str_contains($rule, ':')){
                    $delimiter = strpos($rule, ':');
                    $sMethod = substr($rule, 0, $delimiter);
                    $limit = substr($rule, $delimiter + 1);
                    $method = 'validate' . $sMethod;

                    if(method_exists(self::class, $method)){
                        self::{$method}($parameter, $data[$parameter], $limit);
                    }
                } else{
                    $method = 'validate' . $rule;
                    if(method_exists(self::class, $method)){
                        self::{$method}($parameter, $data[$parameter]);
                    }
                }
            }
        }
        return self::$errors;
    }

    private static function validateRequired($parameter, $value){
        if(!isset($value)){
            self::$errors[$parameter][] = "El $parameter es obligatorio";
        }
    }

    private static function validateArray($parameter, $array){
        if(!is_array($array)){
            self::$errors[$parameter][] = "El $parameter no es un array";
        }
    }

    //==================== Business logic ====================
    private static function validateCorte($parameter, $corte){
        if($corte === null){
            self::$errors[$parameter][] = "No hay corte de caja existente";
        }
        if($corte === false){
            self::$errors[$parameter][] = "No hay corte de caja abierto, Abra un corte para poder tomar pedidos";
        }
    }

    private static function validateId(string $parameter, int $id){
        if($id <= 0){ 
            self::$errors[$parameter][] = "el $parameter no puede ser igual o menor a 0";
        }


    }

    private static function validateCantidad(string $parameter, int $quantity){
        if($quantity < 0){
            self::$errors[$parameter][] = "La $parameter no puede ser menor a 0";
        }
    }
}