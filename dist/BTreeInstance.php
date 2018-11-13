<?php


///@TODO Optimize Saving
///@TODO Lock Files
///@Fix Create method


class BTreeInstance{
    
    
    private $path;
    private $factor;
    private $mx_size;
    private $mn_size;
    private $head;
    private $ext;
    private $null;
    
    public function __construct($path){
        $this->ext = 'btf';
        $this->path = rtrim($path,'/');
        $this->head = $this->openNode($this->path.'/head.'.$this->ext);
        $this->factor = $this->head->factor;
        $this->mn_size = $this->factor-1;
        $this->mx_size = $this->factor*2-1;
        $this->null = (object)[];
    }
    
    public function find($key){
        return $this->rFind($this->head, $key);
    }
    
    public function insert($key, $value){
        return $this->rInsert($this->head, $key, $value, $this->null);
    }
    
    public function remove($key){
        $pN = $this->rFindParent($this->head, $key, $this->null);
        if(!$pN)
            return false;
        
        $this->head->size--;
        $this->saveNode($this->head);
        
        $p = $pN[0];
        $n = $pN[1];
        $i = $pN[2];
        
        return $this->rRemove($n, $i, $p);
    }
    
    
    public static function create($path, $factor){
        
        ///@TODO fix to use variables
        
        //Create Empty Object
        //$obj = createEmptyObject();
        $obj = (object)[];
        $obj->keys = [];
        $obj->values = [];
        $obj->childs = [null];
        
        
        $obj->count = 0;
        $obj->size = 0;
        $obj->factor = $factor;
        $obj->height = 1;
        $obj->path = 'head';
        
        
        //saveNode($obj,$path.'/head.btf');
        //BTree::createPath(dirname($pth));
        file_put_contents($path.'/'.$obj->path.'.btf', json_encode($obj));
        
    }
    
    private function rFindParent(&$node, $key, &$parent){
        if(!$node)
            return null;
        $i=0;
        for(;$i<count($node->keys);$i++){
            $cmp = strcmp($key, $node->keys[$i]);
            if($cmp > 0)
                continue;
            if($cmp === 0)
                return [$parent, $node, $i];
            break;
        }
        $ch = $this->getChild($node,$i);
        if(!$ch)
            return null;
        return $this->rFindParent($ch, $key, $node);
    }
    
    private function rRemove(&$node, $ind, &$parent){
        if(!$node->childs[$ind]){
            if(count($node->keys) > $this->mn_size || !$parent){
                array_splice($node->keys, $ind, 1);
                array_splice($node->values, $ind, 1);
                array_splice($node->childs, $ind, 1);
                $this->saveNode($node);
                return true;
            }
            $lNode = null;
            $rNode = null;
            //if($ind > 0)
            //    $lNode = getChild($parent, $ind-1);
        
            //Get Key
            $key = $node->keys[$ind];
            
            //Remove Node
            $this->removeKey($node, $ind);
            
            //Get Parent Node Index
            $j=0;
            for(;$j<count($parent->keys);$j++){
                $cmp = strcmp($key, $parent->keys[$j]);
                if($cmp > 0)
                    continue;
                break;
            }
            
            //Grab From Right
            if($j+1 < count($parent->childs)){
                $rNode = $this->getChild($parent, $j+1);
                if(count($rNode->keys) > $this->mn_size){
                    //die($j." GRAB RIGHT");
                    $rm = $this->removeKey($rNode, 0);
                    $this->addKey($node, count($node->keys), [$parent->keys[$j], $parent->values[$j], null]);
                    $parent->keys[$j] = $rm[0];
                    $parent->values[$j] = $rm[1];
                    $this->saveNode($parent);
                    $this->saveNode($node);
                    $this->saveNode($rNode);
                    return true;
                }
            }
            
            //Grab From Left
            if($j-1 > 0){
                $lNode = $this->getChild($parent, $j-1);
                if(count($lNode->keys) > $this->mn_size){
                    //die($j." GRAB LEFT");
                    $rm = $this->removeKey($lNode, count($lNode->keys)-1);
                    $this->addKey($node, 0, [$parent->keys[$j-1], $parent->values[$j-1], null]);
                    $parent->keys[$j-1] = $rm[0];
                    $parent->values[$j-1] = $rm[1];
                    $this->saveNode($parent);
                    $this->saveNode($node);
                    $this->saveNode($lNode);
                    return true;
                }
            }
            
            //Merge With Right
            if($rNode){
                //die("MERGE RIGHT");
                $this->addKey($rNode, 0, [$parent->keys[$j], $parent->values[$j], null]);
                $cc = count($node->keys);
                array_splice($rNode->keys, 0, 0, array_splice($node->keys, 0, $cc));
                array_splice($rNode->values, 0, 0, array_splice($node->values, 0, $cc));
                array_splice($rNode->childs, 0, 0, array_splice($node->childs, 0, $cc));
                $this->removeKey($parent, $j);
                $this->removeNode($node);
                $this->saveNode($parent);
                $this->saveNode($rNode);
                return true;
            }
            
            //Merge With Left
            if($lNode){
                //die("MERGE LEFT");
                $this->addKey($node, 0, [$parent->keys[$j-1], $parent->values[$j-1], null]);
                $cc = count($lNode->keys);
                array_splice($node->keys, 0, 0, array_splice($lNode->keys, 0, $cc));
                array_splice($node->values, 0, 0, array_splice($lNode->values, 0, $cc));
                array_splice($node->childs, 0, 0, array_splice($lNode->childs, 0, $cc));
                $this->removeKey($parent, $j-1);
                $this->removeNode($lNode);
                $this->saveNode($parent);
                $this->saveNode($node);
                return true;
            }
        }
        
        $chNode = $this->getChild($node, $ind);
        $dInd = count($chNode->keys)-1;
        $node->keys[$ind] = $chNode->keys[$dInd];
        $node->values[$ind] = $chNode->values[$dInd];
        $this->saveNode($node);
        return $this->rRemove($chNode, $dInd, $node);
    }
    
    private function removeKey(&$node, $ind){
        $k = array_splice($node->keys, $ind, 1)[0];
        $v = array_splice($node->values, $ind, 1)[0];
        $c = array_splice($node->childs, $ind, 1)[0];
        return [$k, $v, $c];
    }
    
    private function addKey(&$node, $ind, $arr){
        array_splice($node->keys, $ind, 0, [$arr[0]]);
        array_splice($node->values, $ind, 0, [$arr[1]]);
        array_splice($node->childs, $ind, 0, [$arr[2]]);
    }
    
    private function debug(&$node){
        echo '<pre>NODE:<br>';
        print_r($node);
        echo '</pre>';
    }
    
    private function fixDir($pth){
        $pth = $this->getNodePath($pth);
        $pth = dirname($pth);
        if(!$this->dirEmpty($pth))
            return;
        rmdir($pth);
        $pth = dirname($pth);
        if(!$this->dirEmpty($pth))
            return;
        rmdir($pth);
    }
    
    private function dirEmpty($dir) {
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                return false;
            }
        }
        return true;
    }
    
    private function rInsert(&$node, $key, $value, &$parent){
        
        $insInd = -1;
        $insNode = &$node;
        $update = false;
        $i=0;
        
        //$this->debug($node);
        
        for(;$i<count($node->keys);$i++){
            $cmp = strcmp($key, $node->keys[$i]);
            if($cmp > 0)
                continue;
            if($cmp === 0){
                $update = true;
                break;
            }
            break;
        }
        $insInd = $i;
        
        if($update){
            $insNode->values[$insInd] = $value;
            $this->saveNode($insNode);
            return false;
        }
        
        if(count($node->keys) == $this->mx_size){
            $newNode = $this->splitNode($node);
            if($node->factor){
                //$node = $newNode[0];
                $insNode = &$newNode[0];
                $newNode = $newNode[1];
            }
            else{
                $this->insertIntoNode($parent, $newNode[0], $newNode[1], $newNode[2]->path);
                $this->saveNode($parent);
                $newNode = $newNode[2];
            }
            if($insInd <= $this->mn_size)
                $insNode = &$newNode;
            else
                $insInd = $insInd-$this->mn_size-1;
        }
        
        $chld = $this->getChild($insNode, $insInd);
        
        if($chld)
            return $this->rInsert($chld, $key, $value, $insNode);
        
        
        array_splice($insNode->keys, $insInd, 0, [$key]);
        array_splice($insNode->values, $insInd, 0, [$value]);
        array_splice($insNode->childs, $insInd, 0, [null]);
        $this->saveNode($insNode);
        
        $this->head->size++;
        $this->saveNode($this->head);
        
        return true;
    }
    
    private function insertIntoNode(&$parent, $key, $val, $path=null){
        $i=0;
        for(;$i<count($parent->keys);$i++){
            $cmp = strcmp($key, $parent->keys[$i]);
            if($cmp > 0)
                continue;
            if($cmp === 0){
                $parent->values[$i] = $val;
                $parent->childs[$i] = $path;
                return;
            }
            break;
        }
        array_splice($parent->keys, $i, 0, [$key]);
        array_splice($parent->values, $i, 0, [$val]);
        array_splice($parent->childs, $i, 0, [$path]);
    }
    
    private function removeNode(&$node){
        unlink($this->getNodePath($node->path));
        $this->fixDir($node->path);
    }
    
    private function splitNode(&$node){

        $leftKeys = array_splice($node->keys,0,$this->mn_size);
        $leftValues = array_splice($node->values,0,$this->mn_size);
        $leftChilds = array_splice($node->childs,0,$this->mn_size+1);
        $leftNode = $this->createNode($leftKeys, $leftValues, $leftChilds);
        $this->saveNode($leftNode);
        $key = $val = null;
        
        if($node->factor){
            $rightKeys = array_splice($node->keys,1);
            $rightValues = array_splice($node->values,1);
            $rightChilds = array_splice($node->childs,0);
            $rightNode = $this->createNode($rightKeys, $rightValues, $rightChilds);
            $node->childs[] = $leftNode->path;
            $node->childs[] = $rightNode->path;
            $this->saveNode($rightNode);
        }else{
            $key = array_splice($node->keys,0,1)[0];
            $val = array_splice($node->values,0,1)[0];
        }
        
        $this->saveNode($node);
        if($node->factor)
            return [$rightNode, $leftNode];
        return [$key, $val, $leftNode];
    }
    
    private function rFind(&$node, $key){
        if($node == $this->null)
            return null;
        $i=0;
        for(;$i<count($node->keys);$i++){
            $cmp = strcmp($key, $node->keys[$i]);
            if($cmp > 0)
                continue;
            if($cmp === 0)
                return $node->values[$i];
            $ch = $this->getChild($node,$i);
            if(!$ch) return null;
            return $this->rFind($ch, $key);
        }
        //echo $i . '-S<br>';
        $ch = $this->getChild($node,$i);
        if(!$ch) return null;
        return $this->rFind($ch, $key);
    }
    
    private function createNode($keys, $values, $childs){
        $name = $this->generateName();
        $fld = substr($name,0,2).'/'.substr($name,2,2).'/'.$name;
        $pth = $this->getNodePath($fld);
        $obj = $this->createEmptyObject();
        $obj->keys = $keys;
        $obj->values = $values;
        $obj->childs = $childs;
        $obj->path = $fld;
        return $obj;
    }
    
    private function generateName(){
        return md5(rand().''.time());
    }
    
    private function createEmptyObject(){
        $obj = (object)[];
        $obj->keys = [];
        $obj->values = [];
        $obj->childs = [null];
        return $obj;
    }
    
    private function getChild(&$node, $ind){
        if(!$node->childs[$ind])
            return null;
        return $this->openNode($this->getNodePath($node->childs[$ind]));
    }
    
    private function getNodePath($pth){
        return $this->path.'/data/'.$pth.'.'.$this->ext;
    }
    
    private function openNode($pth){
        return json_decode(file_get_contents($pth));
    }
    private function saveNode($obj, $pth=''){

        if(!$pth){
            if($obj->path == 'head')
                $pth = $this->path.'/head.'.$this->ext;
            else
                $pth = $this->getNodePath($obj->path);
        }
        
        BTree::createPath(dirname($pth));
        file_put_contents($pth, json_encode($obj));
    }
    
    
}