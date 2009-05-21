<?php

class Swivel6 {
  private $user, $password, $group, $root_url;

  public function __construct($user, $password, $group, $root_url = 'https://api.swivel.com/v1') {
    $this->user = $user;
    $this->password = $password;
    $this->group = $group;
    $this->root_url = $root_url;
  }

  public function charts() {
    return $this->api_get('groups/'.$this->group.'/charts');
  }

  public function create_chart() {
    return $this->api_post('groups/'.$this->group.'/charts');
  }

  public function update_chart($chart, $data) {
    $this->api_put('groups/'.$this->group.'/charts/'.$chart->id, $data);
  }

  public function get_chart_data($chart) {
    return $this->parse_csv($this->req('get','groups/'.$this->group.'/charts/'.$chart->id.'.csv'));
  }

  public function set_chart_data($chart, $data) {
    $this->update_chart($chart, array('data' => $this->create_csv($data)));
  }

  public function append_chart_data($chart, $data) {
    $this->update_chart($chart, array('data' => $this->create_csv($data), 'mode' => 'append'));
  }

  private function api_get($url, $params = false) {
    return $this->xml2object($this->req('get', $url.'.xml', $params));
  }

  private function api_post($url, $params = false) {
    return $this->xml2object($this->req('post', $url.'.xml', $params));
  }

  private function api_put($url, $params = false) {
    $this->req('put', $url.'.xml', $params);
  }

  private function req($type, $url, $params = false) {
    $ch = curl_init($this->root_url.'/'.$url);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
    if($type != 'get') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));

      if($params) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
      }
    }

    $resp = curl_exec($ch);
    curl_close($ch);
    echo $resp;
    return $resp;
  }

  private function create_csv($data) {
    $csv = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');
    foreach($data as $line) {
      fputcsv($csv, $line);
    }
    rewind($csv);
    return stream_get_contents($csv);
  }

  private function parse_csv($data) {
    $csv = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');
    fwrite($csv, $data);
    rewind($csv);
    $ar = array();
    while(($line = fgetcsv($csv)) !== FALSE) {
      $ar[] = $line;
    }
    return $ar;
  }

  # http://us3.php.net/manual/en/function.xml-parse.php#87920
  private function xml2array($contents, $get_attributes = 1, $priority = 'tag') {
    $parser = xml_parser_create('');
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values)
      return; //Hmm...
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array ();
    foreach ($xml_values as $data)
    {
      unset ($attributes, $value);
      extract($data);
      $result = array ();
      $attributes_data = array ();
      if (isset ($value))
      {
        if ($priority == 'tag')
          $result = $value;
        else
          $result['value'] = $value;
      }
      if (isset ($attributes) and $get_attributes)
      {
        foreach ($attributes as $attr => $val)
        {
          if ($priority == 'tag')
            $attributes_data[$attr] = $val;
          else
            $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
        }
      }
      if ($type == "open")
      {
        $parent[$level -1] = & $current;
        if (!is_array($current) or (!in_array($tag, array_keys($current))))
        {
          $current[$tag] = $result;
          if ($attributes_data)
            $current[$tag . '_attr'] = $attributes_data;
          $repeated_tag_index[$tag . '_' . $level] = 1;
          $current = & $current[$tag];
        }
        else
        {
          if (isset ($current[$tag][0]))
          {
            $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
            $repeated_tag_index[$tag . '_' . $level]++;
          }
          else
          {
            $current[$tag] = array (
              $current[$tag],
              $result
            );
            $repeated_tag_index[$tag . '_' . $level] = 2;
            if (isset ($current[$tag . '_attr']))
            {
              $current[$tag]['0_attr'] = $current[$tag . '_attr'];
              unset ($current[$tag . '_attr']);
            }
          }
          $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
          $current = & $current[$tag][$last_item_index];
        }
      }
      elseif ($type == "complete")
      {
        if (!isset ($current[$tag]))
        {
          $current[$tag] = $result;
          $repeated_tag_index[$tag . '_' . $level] = 1;
          if ($priority == 'tag' and $attributes_data)
            $current[$tag . '_attr'] = $attributes_data;
        }
        else
        {
          if (isset ($current[$tag][0]) and is_array($current[$tag]))
          {
            $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
            if ($priority == 'tag' and $get_attributes and $attributes_data)
            {
              $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
            }
            $repeated_tag_index[$tag . '_' . $level]++;
          }
          else
          {
            $current[$tag] = array (
              $current[$tag],
              $result
            );
            $repeated_tag_index[$tag . '_' . $level] = 1;
            if ($priority == 'tag' and $get_attributes)
            {
              if (isset ($current[$tag . '_attr']))
              {
                $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                unset ($current[$tag . '_attr']);
              }
              if ($attributes_data)
              {
                $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
              }
            }
            $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
          }
        }
      }
      elseif ($type == 'close')
      {
        $current = & $parent[$level -1];
      }
    }
    return ($xml_array);
  }

  private function array2object(&$ar) {
    $numeric_array = true;
    foreach($ar as $k => $v) {
      if(is_array($v)) $ar[$k] = $this->array2object($v);
      if(!is_numeric($k)) $numeric_array = false;
    }
    $vals = array_values($ar);

    if(count($ar) == 1 and is_array($vals[0])) {
      return array_shift($ar);
    } else if($numeric_array) {
      return $ar;
    } else {
     return (object) $ar;
    }
  }

  private function xml2object($contents) {
    $ar = $this->xml2array($contents, false);
    $root = array_shift($ar);
    return $this->array2object($root);
  }

}

$s6 = new Swivel6('email@address.com', 'YOUR_PASSWORD', 1000000); // NOTE replace 1000000 with your group number

$newchart = $s6->create_chart();
echo $newchart->name."\n";

$s6->update_chart($newchart, array('title' => 'API Chart', 'name' => 'API Chart', 'description' => 'A chart made automatically with the Swivel API.'));
$s6->set_chart_data($newchart, array(
  array('Monthly Summary','New Topics','New Posts','New Members','Most Online'),
  array('March 2009',922,12133,193,26),
  array('February 2009',888,9061,192,36),
  array('January 2009',689,8654,390,21),
  array('December 2008',408,3375,166,14),
  array('November 2008',5,28,0,3),
  array('October 2008',0,0,2,1),
  array('September 2008',2,2,0,1),
  array('August 2008',14,21,9,2),
  array('July 2008',4,9,6,4)));
$s6->append_chart_data($newchart, array(array('April 2009',271,3372,43,25)));

print_r($s6->get_chart_data($newchart));
echo "Created chart at https://business.swivel.com/charts/".$newchart->id."\n";

$charts = $s6->charts();

foreach($charts as $chart) {
  echo $chart->name.": https://business.swivel.com/charts/".$chart->id."\n";
}

?>