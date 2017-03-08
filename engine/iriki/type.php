<?php

namespace iriki;

class type
{
  public static function is_type($value, $type)
  {
    switch ($type)
    {
      case 'number':
        return is_numeric($value);
      break;

      case 'email':
        return \Mail::isValidEmail($value);
      break;

      case 'string':
        return true;
      break;
      
      case 'boolean':
        if (strtolower($value) == 'true' OR strtolower($value) == 'false')
        {
          return true;
        }
        else return false;
      break;

      default:
        return false;
      break;
    }
  }
}

?>
