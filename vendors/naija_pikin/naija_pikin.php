<?php

//A hopefully funny and non-annoying way to pick random names for unregistered users.

//Basically, it combines an adjective (pulled from http://adjectivesthatstart.com/) and a noun (a Nigerian name - names pulled from faker.ng: https://github.com/binkabir/faker.ng).

//I have not gone thorugh the adjectives to remove offensive ones!

class NaijaPikin
{ 
    private $_json_string = '';
    private $_json = null;

    private $_noun = '';
    private $_adjective = '';

    private static function load_json_file($json_path)
    {
    	if (file_exists($json_path))
    	{
    		$file_contents = file_get_contents($json_path);
    		//do we need to test for contents === FALSE? na!
    		return $file_contents;
    	}
    	else
    	{
    		return '';
    	}
    }

    private static function parse_json_file($json_string)
    {
    	if ($json_string == '')
    	{
    		return null;
    	}
    	else
    	{
    		return json_decode($json_string, TRUE);
    	}
    }

	function __construct($json_path)
    {
        $this->_json_string = NaijaPikin::load_json_file($json_path);
        $this->_json = NaijaPikin::parse_json_file($this->_json_string);
    }

	//pick noun
	public function getNoun($randomize = true, $noun = 'Igwue')
	{
		if ($randomize)
		{
			if (isset($this->_json['nouns']['all']))
			{
				$index = array_rand($this->_json['nouns']['all']);
				$this->_noun = $this->_json['nouns']['all'][$index];
				return $this->_noun;
			}
			else
			{
				$this->_noun = $noun;
				return $noun;
			}
		}
		else
		{
			//use $_noun except empty then used supplied
			if ($this->_noun == '')
			{
				$this->_noun = $noun;
				return $noun;
			}
			else
			{
				return $this->noun;
			}
		}
	}

	//pick adjective
	public function getAdjective($randomize = true, $adjective = 'Irresistable')
	{
		if ($randomize)
		{
			$letter = strtolower($adjective[0]);
			if ($this->_noun !== '') $letter = strtolower($this->_noun[0]);

			if (isset($this->_json['adjectives'][$letter]))
			{
				$index = array_rand($this->_json['adjectives'][$letter]);
				$this->_adjective = $this->_json['adjectives'][$letter][$index];
				$this->_adjective = strtoupper($this->_adjective[0]) . substr($this->_adjective, 1);
				return $this->_adjective;
			}
			else
			{
				$this->_adjective = $adjective;
				return $adjective;
			}
		}
		else
		{
			if ($this->_adjective == '')
			{
				$this->_adjective = $adjective;
				return $adjective;
			}
			else
			{
				return $this->adjective;
			}
		}
	}

	public function getName($randomize = true)
	{
		$noun = $this->getNoun($randomize);
		$adjective = $this->getAdjective(strtolower($noun[0]), $randomize);

		$fullname = $adjective . ' ' . $noun;

		return $fullname;
	}
}

?>