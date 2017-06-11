<?php

namespace iriki;

require_once(__DIR__ . '/config.php');

/**
* Iriki model, capable of self or inhertited actions
*
*/
class model extends config
{
    /**
    * Engine's models amd configuration.
    *
    * @var array
    */
    private $_engine = array(
        'config' => null,
        'models' => null
    );

    /**
    * Application's models amd configuration.
    *
    * @var array
    */
    private $_app = array(
        'config' => null,
        'models' => null
    );

    /**
    * Load model details from configuration files, given an array of routes
    *
    *
    * @param array Configuration key value pairs to get path
    * @param array Defined routes
    * @param string Application or engine name
    * @return array Model details
    * @throw
    */
    private function loadFromJson($config_values, $routes, $app = 'iriki')
    {
        $var = '_engine';
        $path = $config_values['engine']['path'];
        if ($app != 'iriki')
        {
            $var = '_app';
            $path = $config_values['application']['path'];
        }
        $store = &$this->$var;

        foreach ($routes as $route_title => $route_actions)
        {
            $model_json = (new config($path . 'models/' . $route_title . '.json'))->getJson();
            $store['models'][$route_title] = $model_json[$app]['models'][$route_title];
        }

        return $store['models'];
    }

    /**
    * Initialize an application's (engine's too) models
    *
    *
    * @param array Configuration key value pairs to get path
    * @param array Defined routes
    * @param string Application or engine name
    * @return array Model details
    * @throw
    */
    public function doInitialise($config_values, $routes, $app = 'iriki')
    {
        return $this->loadFromJson($config_values, $routes['routes'], $app);
    }

    /**
    * Get application's stored models
    *
    *
    * @param string Application or engine name
    * @return array Model details
    * @throw
    */
    public function getModels($app = 'iriki')
    {
        $var = '_engine';
        if ($app != 'iriki')
        {
            $var = '_app';
        }
        $store = &$this->$var;

        return $store['models'];
    }


    /**
    * Find a model match among supplied models and routes
    * A match is 3 levels:
    * a specific model or alias
    * a specific action for said model
    * a specific set of parameters for said action
    *
    *
    * @param array Model status from request made
    * @param array Defined models
    * @param array Defined routes
    * @return array Model status describing match
    * @throw
    */
    public static function doMatch($model_status, $models = null, $routes = null)
    {
        //see $model_status structure in route->matchUrl

        $model = (isset($model_status['str']) ? $model_status['str'] : null);

        //find model details
        foreach ($models as $_model => $_action)
        {
            if ($_model == $model_status['str'])
            {
                //we have found the model
                $model_status['details'] = array(
                    'description' => $_action['description'],
                    'properties' => $_action['properties'],
                    'relationships' => $_action['relationships']
                );

                //now, to find the model's route
                foreach ($routes['routes'] as $_route => $_route_action)
                {
                    if ($_route == $model_status['str'])
                    {
                        //model's route found, look up action
                        if (isset($_route_action[$model_status['action']]))
                        {
                            $model_status['action_details'] = array(
                                'description' => (isset($_route_action[$model_status['action']]['description']) ? $_route_action[$model_status['action']]['description'] : ''),
                                'parameters' => $_route_action[$model_status['action']]['parameters'],
                                'exempt' => (isset($_route_action[$model_status['action']]['exempt']) ? $_route_action[$model_status['action']]['exempt'] : array()),
                                'authenticate' => (isset($_route_action[$model_status['action']]['authenticate']) ? $_route_action[$model_status['action']]['authenticate'] : "true")
                            );

                            $model_status['action_defined'] = true;
                            $model_status['action_default'] = false;

                            break;
                        }
                        //test for action in default
                        else if (isset($model_status['default'][$model_status['action']]))
                        {
                            $model_status['action_details'] = array(
                                'description' => (isset($model_status['default'][$model_status['action']]['description']) ? $model_status['default'][$model_status['action']]['description'] : ''),
                                'parameters' => $model_status['default'][$model_status['action']]['parameters'],
                                'exempt' => (isset($model_status['default'][$model_status['action']]['exempt']) ? $model_status['default'][$model_status['action']]['exempt'] : array()),
                                'authenticate' => (isset($_route_action[$model_status['action']]['authenticate']) ? $_route_action[$model_status['action']]['authenticate'] : "true")
                            );

                            $model_status['action_defined'] = true;
                            $model_status['action_default'] = true;

                            break;
                        }
                        else
                        {
                            //action not found
                            $model_status['action_defined'] = false;
                            $model_status['action_default'] = false;

                            //default to description of model since action does not exist

                            $model_status['action_details'] = array(
                                'description' => $_action['description']
                            );

                            break;
                        }
                    }
                }
            }
        }

        return $model_status;
    }


    /**
    * Match a model's defined parameters to those sent via the request
    * Note that a parameter failing a type check is 'missing'
    *
    *
    * @param array Model property details
    * @param array Parameters sent via request
    * @param array Route filters: parameters and exempt
    * @return array Properties: final, missing, extra and ids
    * @throw
    */
    public static function doPropertyMatch($details, $sent, $filter)
    {
        //parameters work thus:
        //empty valid => all paramters valid except 'exempt'
        //non-empty valid => listed parameters except 'exempt'

        $all_properties = $details['properties'];

        $valid_properties = $filter['parameters'];
        $exempt_properties = (isset($filter['exempt']) ? $filter['exempt'] : null);

        //build sent properties
        $sent_properties = array_keys($sent);

        //build valid properties
        if (count($valid_properties) == 0)
        {
            //all properties are valid
            $valid_properties = array_keys($all_properties);
        }

        //check exempt properties
        if (count($exempt_properties) == 0)
        {
            //there's no except list, carry on
        }
        else
        {
            for ($i = count($valid_properties) - 1; $i >= 0; $i--)
            {
                if (in_array($valid_properties[$i], $exempt_properties))
                {
                    unset($valid_properties[$i]);
                }
            }
        }


        //check for valid sent properties
        $properties_missing = array();
        $final_properties = array();
        foreach ($valid_properties as $property)
        {
            if (isset($sent[$property]))
            {
                //property is valid and was sent

                //check type? note that the property might be that of a parent model
                if (isset($all_properties[$property]['type']))
                {
                  $type = $all_properties[$property]['type'];  //might be absent
                  $value = $sent[$property];

                  if (type::is_type($value, $type))
                  {
                    $final_properties[] = $property;
                  }
                  else
                  {
                    //a supplied property of different type is deemed missing
                    $properties_missing[] = $property;
                  }
                }
                else
                {
                  //ignore type check then
                }
            }
            else
            {
                $properties_missing[] = $property;
            }
        }

        //check for invalid sent properties
        $extra_properties = array();
        foreach ($sent_properties as $index => $property)
        {
            if (FALSE !== array_search($property, $valid_properties))
            {
                //property sent is valid
            }
            else
            {
                $extra_properties[] = $property;
            }
        }

        return array(
            //properties supplied
            'final' => $final_properties,
            //missing properties that should have been supplied
            'missing' => $properties_missing,
            //extra properties that should not have been supplied
            'extra' => $extra_properties,
            //these, especially for mongodb have to be saved as mongoids
            'ids' => array()
        );
    }


    /**
    * Check properties in supplied parameters that need to be unique
    *
    *
    * @param object Request object encapsulating necessary details
    * @return array Pre-existing properties
    * @throw
    */
    public static function doParameterUniqueCheck($request)
    {
      $existing = array();

      $model_status = $request->getModelStatus();
      $final_properties = $request->getParameterStatus()['final'];
      $final_values = $request->getData();

      $properties = null;
      if ($model_status['action_defined'])
      {
        $properties = $model_status['details']['properties'];
      }
      else if ($model_status['action_default'])
      {
        $properties = $model_status['default']['properties'];
      }

      //$request will be modified, save it here
      $initial_request = clone $request;

      foreach ($final_properties as $index => $property)
      {
        if (isset($properties[$property]))
        {
          $property_details = $properties[$property];

          //check unique
          if (isset($property_details['unique']))
          {
            $new_request = $initial_request;
            $new_request->setData(array($property => $final_values[$property]));
            //parameters
      			$new_request->setParameterStatus(array(
      				'final' => array($property),
      				'missing' => array(),
      				'extra' => array(),
      				'ids' => $initial_request->getParameterStatus()['ids']
      			));

            $found = $new_request->read($new_request, false);

            //revert to original here
            $request = $initial_request;
            if (count($found) != 0) $existing[] = $property;
          }
        }
      }
      return $existing;
    }

    /**
    * Check a model for 'belongsto' parent relationship.
    * Returns a modified parameter statuses you may have to update.
    *
    * @param object Request
    * @return array Modified parameter status
    * @throw
    */
    public static function doBelongsToRelation($request)
    {
      //test to see if request can be sent by reference so we can convert parent model ids to mongoid
      $parameters = $request->getParameterStatus();

      $belongsto = array();
      $belongsto = (isset($request->getModelStatus()['details']['relationships']['belongsto']) ? $request->getModelStatus()['details']['relationships']['belongsto'] : array());

      $request_data = $request->getData();

      if (count($belongsto) != 0)
      {
        foreach ($belongsto as $parent_model)
        {
          //all parent models must have a 'parent_model + id_field' parameter supplied
          //we could go as far as to check that the parent model exists but... maybe not


            //the plan is simple
            //if the model 'user_session' belongsto 'user'
            //the user_session model will have a user_id field, get it
            //then find the user details with the id supplied

          $db_instance = $request::getDBInstance();

          $property_identifier = $parent_model . $db_instance::ID_FIELD;
          
          if (isset($request_data[$property_identifier]))
          {
            //add to final parameters
            $parameters['final'][] = $property_identifier;

            //add to ids data
            $parameters['ids'][] = $property_identifier;

            //pull out supplied from extra parameters
            $extra_key = array_search($property_identifier, $parameters['extra']);
            if ($extra_key !== FALSE)
            {
              unset($parameters['extra'][$extra_key]);
            }
          }
          else
          {
            //note that it is missing
            $parameters['missing'][] = $property_identifier;
          }
        }
      }
      
      return $parameters;
    }

    /**
    * Check a model for 'hasmnay' child relationship.
    * Will have to make several reads up to a recursivity limit.
    *
    * @param object Request
    * @return array Parameter status for now, should change soon
    * @throw
    */
    public static function doHasManyRelation($request, $recursivity = 1)
    {
      //test to see if request can be sent by reference so we can convert parent model ids to mongoid
      $parameters = $request->getParameterStatus();

      $hasmany = array();
      $hasmany_data = array();
      $hasmany = (isset($request->getModelStatus()['details']['relationships']['hasmany']) ? $request->getModelStatus()['details']['relationships']['hasmany'] : array());

        //the plan is simple
        //if the model 'user' hasmany 'user_session'
        //the user model will have an _id field: the user_id, get it
        //then find the user_sessions with the user_id supplied

      /*if (count($hasmany) != 0)
      {
        $parent_model = $request->getModelStatus()['str']; //this present model
        $db_instance = &$request::getDBInstance();
        $property_identifier = $parent_model . $db_instance::ID_FIELD;
        $property_value = $request->getData()[$db_instance::ID_FIELD];
        

        foreach ($hasmany as $child_model)
        {
          //build request to child model

          //read data from child model
          //$hasmany_data[$child_model] = $request->

          //modify parameters
          //final remains same
          //missing remains same so already reported if not count 0
          //extra remains same, but report it as it hasn't been yet
        }
      }*/

      return $parameters;
    }

    /**
    * Get status, a summary of model details
    *
    *
    * @param array Previous status array to append to
    * @param boolean Encode result as json
    * @return array Status array or json representation
    * @throw
    */
    public function getStatus($status = null, $json = false)
    {
        if (is_null($status))
        {
            $status = array('data' => array());
        }

        //engine's models
        $status['data']['engine']['models'] = array();
        foreach ($this->_engine['models'] as $model => $details)
        {
            $status['data']['engine']['models'][] = $model;
        }

        //app's models
        $status['data']['application']['models'] = array();
        foreach ($this->_app['models'] as $model => $details)
        {
            $status['data']['application']['models'][] = $model;
        }

        if ($json)
        {
            return json_encode($status);
        }
        else
        {
            return $status;
        }
    }

}

?>