<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TermModel extends Model
{
	use CacheModel;

	public static $unique = "name";
	public $array = [];
	public $related = [];
	public $related_text = [];
	public $readonly = [];
	public $initData = [];

	protected $extend_fields = [];
	public function extend_fields() { return []; }
	public function extend_related() { return []; }
	public function extend_object($name) {
		$class = "App\\" . $name;
		$field = strtolower($name);
		return $class::cacheUniqueObject($this->{$field});
	}

	protected function array_field_list($field) {
		$fn = explode("|", $this->{$field});
		if (!isset($this->related[$field])) {
			return $fn;
		}
		$class_name = "App\\" . $this->related[$field];
		$objects = [];
		foreach ($fn as $n) {
			$o = $class_name::objectByIdOrName($n);
			if ($o) $objects[$o->name] = $o;
		}
		return $objects;
	}

	public function newQuery() {
		$query = parent::newQuery();
		$query->where('status', 1)
			->where('schoolterm', static::school_term()->id);
		return $query;
	}

	public function appendValue($field, $value) {
		if (strlen($this->$field) == 0) {
			$this->$field = $value;
			return true;
		}
		$values = explode("|", $this->$field);
		if (!in_array($value, $values)) {
			$values[] = $value;
			$this->$field = join("|", $values);
			return true;
		}
		return false;
	}

	public static function activeWhere($key = null, $opt = null, $val = null) {
		if (is_null($val)) {
			$val = $opt;
			$opt = "=";
		}
		if (is_null($key) || is_null($val)) {
			return static::where('status', 1)
				->where('schoolterm', static::school_term()->id);
		} else {
			return static::where('status', 1)
				->where('schoolterm', static::school_term()->id)
				->where($key, $opt, $val);
		}
	}

	public static function object($id) {
		return static::activeWhere('id', $id)->first();
	}

	public static function uniqueObject($uniqueValue) {
		if (!static::$unique) return null;
		return static::activeWhere(static::$unique, $uniqueValue)->first();
	}

	public static function selectObjects() {
		$values = [];
		foreach(static::activeWhere()
			->lists('id', static::$unique)->all() as $k => $id) {
			$values["id:{$id}"] = $k;
		}
		return $values;
	}

	public static function convertValue($v) {
		if (strpos($v, "id:") === 0) {
			$id = intval(substr($v, 3));
			$o = static::cacheObject($id);
			return $o ? $o->{static::$unique} : "<" . $v . ">";
		} else {
			$o = static::cacheUniqueObject($v);
		}
		return !$o ? "<" . $v . ">" : null;
	}

	public static function id_parse($id) {
		return intval(substr($id, 3));
	}

	public static function checkObject($obj, $val) {
		if ($obj->id == $val) return true;
		if ($val == "id:{$obj->id}") return true;
		if ($obj::$unique && $obj->{$obj::$unique} == $val) {
			return true;
		}
		return false;
	}

	public static function objectByIdOrName($v) {
		if (!static::$unique) return null;
		if (strpos($v, "id:") === 0) {
			$id = self::id_parse($v);
			return static::cacheObject($id);
		} else {
			return static::cacheUniqueObject($v);
		}
	}

	public static function convertIdOrName($v, $toId = true) {
		$o = static::objectByIdOrName($v);
		return $o ? ($toId ? "id:" . $o->id : $o->{static::$unique}) : $v;
	}

	private static $schoolterm;
	protected static function school_term() {
		if (self::$schoolterm) return self::$schoolterm;
		$schoolterm = (new SchoolTerm())->cache_get();
		if (!$schoolterm) {
			$schoolterm = SchoolTerm::current_term();
			if (!$schoolterm) {
				$schoolterm = new SchoolTerm();
				$schoolterm->id = 1;
			}
			$schoolterm->cache_put();
			return self::$schoolterm = $schoolterm;
		} else {
			return self::$schoolterm = unserialize($schoolterm);
		}
	}

	public function editable() {
		return array_diff($this->fillable, ['schoolterm']);
	}

	public static function canRegister() {
		return false;
	}
}
