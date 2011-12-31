<?php
/**
 * XML and HTML classes to facilitate the making of HTML pages
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package xml
 */

/**
 * Interface for HTML elements: specifies toXML method
 */
interface HTMLElement
{
  public function toXML();
  public function printXML();
}


/**
 * Generic object for HTML Element.
 *
 * Prescribes ways to get HTML as well as
 * attributes, and children nodes
 */
class GenericElement implements HTMLElement
{
  // Private variables
  protected $elemName;
  protected $elemAttrs;
  protected $elemChildren;

  public function __construct($e,
			      $child = array(),
			      $attrs = array()) {
    // Initialize variables
    $this->elemName = $e;

    $this->elemChildren = array();
    foreach ($child as $c)
      $this->add($c);
    $this->elemAttrs = array();
    foreach ($attrs as $k => $val) {
      if (!is_array($val))
	$val = array($val);
      foreach ($val as $v)
	$this->set($k,$v);
    }
  }

  public function getAttrs()    { return $this->elemAttrs; }
  public function getChildren() { return $this->elemChildren;}
  public function getName() { return $this->elemName; }

  public function add($e) {
    $this->elemChildren[] = $e;
  }
  public function set($name, $value) {
    $this->elemAttrs[$name][] = $value;
  }

  // Like adding attribute, except it does not append
  public function setAttr($name, $value) {
    $this->elemAttrs[$name] = array($value);
  }
  // Removes entire attribute from element
  public function removeAttr($name) {
    unset($this->elemAttrs[$name]);
  }

  // Generic toXML method
  public function toXML() {
    $str = "<" . $this->elemName;
    // Process attributes
    foreach ($this->getAttrs() as $attr => $value) {
      if (!empty($value)) {
	$str .= " $attr=\"";
	$str .= implode(" ", $value) . "\"";
      }
    }
    if (count($this->getChildren()) == 0)
      return $str . "/>";

    $str .= ">";
    $child_str = "";
    // Process children
    foreach ($this->getChildren() as $child) {
      $str .= $child->toXML();
    }
    // Close tag
    $str .= sprintf("</%s>", $this->elemName);
    return $str;
  }

  // Generic printXML method
  public function printXML() {
    echo "<" . $this->elemName;
    // Process attributes
    foreach ($this->getAttrs() as $attr => $value) {
      if (!empty($value)) {
	echo " $attr=\"";
	echo implode(" ", $value) . "\"";
      }
    }
    if (count($this->getChildren()) == 0) {
      echo "/>";
      return;
    }
    echo ">";
    // Process children
    foreach ($this->getChildren() as $child)
      $child->printXML();

    // Close tag
    echo sprintf("</%s>", $this->elemName);
  }

  /**
   * toString representation
   */
  public function __toString() {
    return sprintf("HTMLComp: %s", $this->elemName);
  }

  /**
   * creates clone of this object and its children
   */
  public function __clone() {
    foreach($this->elemChildren as $key => $child) {
      $this->elemChildren[$key] = clone($this->elemChildren[$key]);
    }
  }
}

/**
 * Generic HTML Page, with head and body elements built in
 */
class WebPage extends GenericElement
{
  // Variables
  protected $head; // head element
  protected $body; // body element

  public function __construct() {
    parent::__construct("html",
			array(),
			array("xmlns"=>"http://www.w3.org/1999/xhtml",
			      "xml:lang"=>"en",
			      "lang"=>"en"));
    $this->head = new GenericElement("head");
    $this->body = new GenericElement("body");

    $this->add($this->head);
    $this->add($this->body);
  }

  public function addHead($e) {
    $this->head->add($e);
  }
  public function addBody($e) {
    $this->body->add($e);
  }

  public function __get($e) {
    if ($e == "body" || $e == "head")
      return $this->$e;
  }

  public function toXML() {
    $str = '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
    $str .= parent::toXML();
    return $str;
  }

  public function printXML() {
    echo '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
    parent::printXML();
  }
}

/**
   Holds information in a "table" which can then be printed to HTML,
   or otherwise parsed through.

   @author Dayan Paez
   @version   February 3, 2009
*/

class Table extends GenericElement
{
  // Variables
  private $header; // <thead>
  private $body;   // <tbody>
  private $bodyrows;
  private $headrows;

  public function __construct($rows  = array(),
			      $attrs = array()) {
    parent::__construct("table",
			array(),
			$attrs);

    $this->header = new GenericElement("thead");
    $this->body   = new GenericElement("tbody");

    $this->add($this->header);
    $this->add($this->body);

    foreach ($rows as $r) {
      $this->addRow($r);
    }
  }

  // Add rows to the <tbody> element
  // takes multiple arguments
  public function addRow($row) {
    foreach (func_get_args() as $r) {
      // Check for valid entry
      if ( !($r instanceof Row) ) {
	trigger_error("Variable is not a Row object", E_USER_WARNING);
      }
      else {
	$this->body->add($r);
      }
    }
  }

  // Add rows to <thead> element
  // takes multiple arguments
  public function addHeader($row) {
    foreach (func_get_args() as $r) {
      // Check for valid entry
      if ( !($r instanceof Row) ) {
	trigger_error("Variable is not a Row object", E_USER_WARNING);
      }
      else {
	$this->header->add($r);
      }
    }
  }

  // Get rows
  public function getRows() {
    return $this->body->getChildren();
  }
  public function getHeaders() {
    return $this->header->getChildren();
  }
}




/**
   Represents a table row

   @author Dayan Paez
   @version   February 3, 2009
*/

class Row extends GenericElement
{
  // $cells is an array of cells to add to this row
  // $attrs are attributes to add to this row
  // see Cell constructor
  public function __construct($cells = array(),
			      $attrs = array()) {
    parent::__construct("tr",
			array(),
			$attrs);
    foreach ($cells as $c)
      $this->addCell($c);
  }

  public function addCell($cell) {
    foreach (func_get_args() as $c) {
      if (!($c instanceof Cell)) {
	throw new InvalidArgumentException("Argument is not a Cell object");
	trigger_error("Argument is not a Cell object", E_USER_WARNING);
      }
      else {
	$this->add($c);
      }
    }
  }
  public function getCells() {
    return $this->getChildren();
  }
}


/**
   Encapsulates a table Cell object

   @author Dayan Paez
   @version   February 3, 2009
*/
class Cell extends GenericElement
{
  // Constructor for cell
  // $value is cell value
  // $type  is 0 for <td> and 1 for <th>
  // $attrs is array of attributes, e.g.:
  // 
  //   $attrs = array("id"    => "somecell",
  //                  "title" => "celltitle");
  public function __construct($value = "",
			      $attrs = array(),
			      $type  = 0) {
    if ($type == 0)
      $t = "td";
    else
      $t = "th";
    parent::__construct($t, 
			array(),
			$attrs);
    if (is_object($value))
      $this->add($value);
    else
      $this->add(new XText($value));
  }

  public function addText($val) {
    $this->add(new XText($val));
  }
  
  public function is_header() {
    return ($this->type != 0);
  }

  // Returns new cell object of the specified type
  public static function td($value = "") {
    return new Cell($value);
  }

  public static function th($value = "") {
    return new Cell($value,array(),1);
  }

}

/**
 * Div object, of CSS class "port" used 
 * to organize pages
 */
class Port extends GenericElement
{
  private $title;
  public function __construct($title = "", 
			      $value = array(), 
			      $attrs = array()) {
    parent::__construct("div",
			array_merge(array($this->title = new XH3($title)),
				    $value),
			$attrs);
    $this->set("class","port");
  }

  public function addHelp($href) {
    $this->title->add(new XHLink($href));
  }
}

/**
 * Portlets, instead of ports, which have CSS class "small"
 */
class Portlet extends Port
{
  public function __construct($title = "",
			      $value = array(),
			      $attrs = array()) {
    parent::__construct($title, $value, $attrs);
    $this->set("class","small");
  }
}

/**
 * Generic form input
 */
class FGenericElement extends GenericElement
{
  // private variables
  protected $formName;
  protected $formValue; // array
  
  // $t is the tagname of the form element
  // $n is the name attribute of the form elment
  // $v is the (default) value
  public function __construct($t,
			      $n,
			      $v = array(),
			      $attrs = array()) {
    parent::__construct($t,
			array(),
			$attrs);

    // Initialize value variable empty array
    $this->formValue = array();

    $this->setName($n);
    foreach ($v as $val)
      $this->addValue($val);
  }
  public function getDefaultValue(){ return $this->formValue;}

  public function setName($n) {
    $this->formName = $n;
    $this->set("name",$n);
  }
  public function addValue($v) {
    $this->formValue[] = $v;
    $this->set("value",$v);
  }
}

/**
 * From select input (allows multiple selects)
 */
class FSelect extends FGenericElement
{
  public function __construct($name,
			      $value = array(),
			      $attrs = array()) {
    parent::__construct("select",
			$name,
			$value,
			$attrs);
  }

  // Options should be given as keyed arrays with keys equal to option
  // "value" and array value equal to option "label", all strings
  public function addOptions($opt) {
    foreach ($opt as $val => $label) {
      $this->add(new Option($val, $label));
    }
  }

  // Adds option group <optgroup>, with label and options
  public function addOptionGroup($label, $opt) {
    $o = new OptionGroup($label);
    foreach ($opt as $val => $label) {
      $o->add(new Option($val, $label));
    }
    $this->add($o);
  }

  // Overrides addChild method
  public function add($e) {
    if (($e instanceof OptionGroup)) { // children MUST be options
      foreach ($e->getChildren() as $option) {
	if (in_array($option->getOptionValue(),
		     $this->getDefaultValue()))
	  $option->select();
	else
	  $option->select(false);
      }
    }
    elseif ($e instanceof Option) {
      if (in_array($e->getOptionValue(),
		   $this->getDefaultValue()))
	$e->select();
      else
	$e->select(false);
    }
    else {
      echo get_class($e);
      // Children must be either options or optiongroups
      trigger_error("Select element must have option or option " . 
		    "groups as children",
		    E_USER_ERROR);
    }
    // Add, finally
    parent::add($e);
  }

}

/**
 * Option group <optgroup>.
 */
class OptionGroup extends GenericElement
{
  public function __construct($label,
			      $value = array(),
			      $attrs = array()) {
    parent::__construct("optgroup",
			$value,
			$attrs);
    $this->set("label", $label);
  }

}

/**
 * Option for select elements <option>
 */
class Option extends GenericElement
{
  public function __construct($value = "",
			      $content = "",
			      $attrs = array()) {
    parent::__construct("option",
			array(new XText($content)),
			$attrs);
    $this->set("value", $value);
  }

  // Selects/deselects this option
  public function select($state = true) {
    if ($state)
      $this->setAttr("selected", "selected");
    else
      $this->removeAttr("selected");
  }

  public function getOptionValue() {
    return $this->elemAttrs["value"][0];
  }
}
?>
