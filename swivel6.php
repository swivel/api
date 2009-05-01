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
    return $this->api_post('groups/'.$this->group.'/charts',
      array('grid' => array('name' => 'Chart Data')));
  }

  public function update_chart($chart, $data) {
    $this->api_put('groups/'.$this->group.'/charts/'.$chart->id,
      array('chart' => $data));
  }

  public function chart_data($chart) {
    $grid_id = $this->get_chart_grid_id($chart);
    return $this->get_data($grid_id);
  }

  public function set_chart_data($chart, $data) {
    $cells = array();
    foreach($data as $rownum => $row) {
      foreach($row as $colnum => $cell) {
        $cells[] = array("$colnum,$rownum", array('v' => $cell));
      }
    }

    $save_list = array(array('action' => 'edit', 'cell_values' => $cells));
    $grid_id = $this->get_chart_grid_id($chart);

    $this->api_put('grids/'.$grid_id, array('tabular' => array('save_list' => json_encode($save_list))));
  }

  public function clear_chart_data($chart) {
    $data = $this->chart_data($chart);
    $num_rows = count($data);

    if($num_rows > 0) {
      $grid_id = $this->get_chart_grid_id($chart);
      $save_list = array(array('action' => 'removeRows', 'start' => 0, 'end' => $num_rows - 1));
      $this->api_put('grids/'.$grid_id, array('tabular' => array('save_list' => json_encode($save_list))));
    }
  }

  public function insert_chart_row($chart, $position) {
    $grid_id = $this->get_chart_grid_id($chart);
    $save_list = array(array('action' => 'insertRows', 'rows' => '', 'position' => $position));
    $this->api_put('grids/'.$grid_id, array('tabular' => array('save_list' => json_encode($save_list))));
  }

  public function set_chart_row($chart, $rownum, $data) {
    $cells = array();
    foreach($data as $colnum => $cell) {
      $cells[] = array("$colnum,$rownum", array('v' => $cell));
    }

    $save_list = array(array('action' => 'edit', 'cell_values' => $cells));
    $grid_id = $this->get_chart_grid_id($chart);

    $this->api_put('grids/'.$grid_id, array('tabular' => array('save_list' => json_encode($save_list))));
  }

  private function api_get($url, $params = false) {
    return json_decode($this->req('get', $url.'.json', false));
  }

  private function api_post($url, $params = false) {
    return json_decode($this->req('post', $url.'.json', $params));
  }

  private function api_put($url, $params = false) {
    $this->req('put', $url.'.json', $params);
  }

  private function req($type, $url, $params) {
    $ch = curl_init($this->root_url.'/'.$url);
//    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); 
    curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
    if($type != 'get') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
      curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));

      if($params) {
        $root = array_shift(array_keys($params));
        $xml = new XmlWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($root);

        $this->array2xml($params[$root], $xml);

        $xml->endElement();
        $xml_str = $xml->outputMemory(true);
//        echo $xml_str;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_str);
      }
    }

    $resp = curl_exec($ch);
    curl_close($ch);
//    echo $resp;
    return $resp;
  }

  private function array2xml($data, XMLWriter $xml){
    foreach($data as $key => $value){
      if(is_array($value)){
        $xml->startElement($key);
        $this->array2xml($xml, $value);
        $xml->endElement();
        continue;
      }
      $xml->writeElement($key, $value);
    }
  }

  private function get_data($tabular_id) {
    $cells = $this->api_get('tabulars/'.$tabular_id.'/cells');
    $max_row = -1;
    $max_col = -1;

    foreach($cells as $cell) {
      if($cell->r > $max_row) $max_row = $cell->r;
      if($cell->c > $max_col) $max_col = $cell->c;
    }

    $data = array();
    for($r = 0; $r < $max_row + 1; $r++) {
      $row = array();
      $row = array_pad($row, $max_col + 1, NULL);
      $data[] = $row;
    }

    foreach($cells as $cell) {
      $data[$cell->r][$cell->c] = $cell->formatted;
    }

    return $data;
  }

  private function get_chart_grid_id($chart) {
    $grid = $this->api_get('groups/'.$this->group.'/charts/'.$chart->id.'/grid');
    return $grid->id;
  }

}

$s6 = new Swivel6('email@address.com', 'YOUR_PASSWORD', 1000000); // NOTE replace 1000000 with your group number

$newchart = $s6->create_chart();
echo $newchart->name."\n";

$s6->update_chart($newchart, array('title' => 'API Chart', 'name' => 'API Chart', 'description' => 'A chart made automatically with the Swivel API.'));

$s6->clear_chart_data($newchart);
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
  array('July 2008',4,9,6,4),
));
$s6->insert_chart_row($newchart, 1);
$s6->set_chart_row($newchart, 1, array('April 2009',271,3372,43,25));

print_r($s6->chart_data($newchart));
echo "Created chart at https://business.swivel.com/charts/".$newchart->id."\n";

$charts = $s6->charts();
foreach($charts as $chart) {
  echo $chart->name.": https://business.swivel.com/charts/".$chart->id."\n";
}

?>