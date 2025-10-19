<?php declare(strict_types=1);
// File: src/Util/ReflectionPrinter.php
namespace Survos\StepBundle\Util;

use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class ReflectionPrinter
{
    public static function signature(ReflectionFunctionAbstract $m): string
    {
        $params = array_map(fn($p)=>self::param($p), $m->getParameters());
        $ret = $m->hasReturnType() ? ': '.self::type($m->getReturnType()) : '';
        return $m->getName().'('.implode(', ', $params).')'.$ret;
    }

    private static function param(ReflectionParameter $p): string
    {
        $s='';
        if($p->hasType()) $s.=self::type($p->getType()).' ';
        if($p->isPassedByReference()) $s.='&';
        if($p->isVariadic()) $s.='...';
        $s.='$'.$p->getName();
        if($p->isDefaultValueAvailable()){
            try{$v=$p->getDefaultValue();$s.=' = '.self::export($v);}
            catch(\Throwable){$s.=' = null';}
        }
        return $s;
    }

    private static function type(?ReflectionType $t): string
    {
        if(!$t)return'';
        if($t instanceof ReflectionNamedType){
            $n=$t->getName();
            return $t->allowsNull()&&!$t->isBuiltin()?'?'.$n:$n;
        }
        if($t instanceof ReflectionUnionType)
            return implode('|',array_map(fn($x)=>self::type($x),$t->getTypes()));
        if($t instanceof ReflectionIntersectionType)
            return implode('&',array_map(fn($x)=>self::type($x),$t->getTypes()));
        return (string)$t;
    }

    private static function export(mixed $v): string
    {
        return match(true){
            is_string($v)=>"'".addslashes($v)."'",
            is_bool($v)=>$v?'true':'false',
            is_null($v)=>'null',
            is_array($v)=>'['.implode(', ',array_map(fn($x)=>self::export($x),$v)).']',
            is_int($v),is_float($v)=>(string)$v,
            default=>json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'null'
        };
    }
}
