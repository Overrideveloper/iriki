<?php

namespace hr_log;

class staff extends \iriki\model
{

  public function read_all($params_persist = null)
  {
    $instance = new $params_persist['db_type']();
    $instance::initInstance();

    $params_persist['data'] = array();

    if (!is_null($params_persist))
    {
      return $instance::doRead($params_persist);
    }

  }
}

?>
