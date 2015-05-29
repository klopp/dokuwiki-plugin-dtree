<?php
/**
 * Plugin DTree  
 * Based on catlist (https://www.dokuwiki.org/plugin:catlist) by Félix Faisant
 */
if( !defined( 'DOKU_INC' ) ) die( 'foobar' );
if( !defined( 'DOKU_PLUGIN' ) ) define( 'DOKU_PLUGIN', 
        DOKU_INC . 'lib/plugins/' );
if( !defined( 'DOKU_URL' ) ) define( 'DOKU_URL', getBaseURL( true ) );
require_once DOKU_PLUGIN . 'syntax.php';

class syntax_plugin_dtree extends DokuWiki_Syntax_Plugin
{

    /* -------------------------------------------------------------------------------- */
    public function __construct()
    {
        $this->idx = mt_rand( 1, 999 );
        $this->xml_tree_data = array();
        
        if( function_exists( 'parent::__construct' ) )
        {
            parent::__construct();
        }
    }

    /* -------------------------------------------------------------------------------- */
    function connectTo($aMode)
    {
        $this->Lexer->addSpecialPattern( '<dtree\b[^>]*>.*?<\/dtree>', $aMode, 
                'plugin_dtree' );
    }

    /* -------------------------------------------------------------------------------- */
    function getSort()
    {
        return 111;
    }

    /* -------------------------------------------------------------------------------- */
    function gettype()
    {
        return 'substition';
    }

    /* -------------------------------------------------------------------------------- */
    function handle($match, $state, $pos, &$handler)
    {
        $data = array('xml' => $match,'safe' => true,'error' => false,
                        
                        'title' => false,'showhead' => true,'ns' => '',
                        'nopages' => false,'width' => '100%','height' => '100%',
                        'idx' => $this->idx,'skin' => $this->getConf( 'skin' ) 
        );
        
        $this->idx = mt_rand( 1, 999 );
        return $data;
    }

    /* -------------------------------------------------------------------------------- */
    function _isExcluded($item, $excludes)
    {
        return in_array( $item['id'], $excludes );
    }

    /* -------------------------------------------------------------------------------- */
    function _bool($val)
    {
        $val = strtolower( $val );
        if( !$val || $val == 'false' || $val == 'no' || $val == 'off' ||
                 $val == 'none' )
        {
            return false;
        }
        return $val;
    }

    /* -------------------------------------------------------------------------------- */
    function _parseConfig($data)
    {
        $data['openlevel'] = 0;
        $data['skin'] = $this->getConf( 'skin' );
        $data['openall'] = $this->getConf( 'openall' );
        $data['style'] = $this->getConf( 'style' );
        $data['class'] = $this->getConf( 'class' );
        
        $data['head'] = preg_split( '/[\s,;]+/', $this->getConf( 'head' ), -1, 
                PREG_SPLIT_NO_EMPTY );
        $data['tail'] = preg_split( '/[\s,;]+/', $this->getConf( 'tail' ), -1, 
                PREG_SPLIT_NO_EMPTY );
        $data['xns'] = preg_split( '/[\s,;]+/', $this->getConf( 'xns' ), -1, 
                PREG_SPLIT_NO_EMPTY );
        $data['xpage'] = preg_split( '/[\s,;]+/', $this->getConf( 'xpage' ), 
                -1, PREG_SPLIT_NO_EMPTY );
        $data['open'] = preg_split( '/[\s,;]+/', $this->getConf( 'open' ), -1, 
                PREG_SPLIT_NO_EMPTY );
        $data['close'] = preg_split( '/[\s,;]+/', $this->getConf( 'close' ), 
                -1, PREG_SPLIT_NO_EMPTY );
        
        $xml = simplexml_load_string( $data['xml'] );
        if( !isset( $xml ) || !$xml )
        {
            $data['error'] = 'Error parsing XML data';
            $this->_plog( "DATA ERROR : " . $data['error'] );
            msg( $data['error'], -1 );
            return $data;
        }
        /*
         
         <img border="0" align="absmiddle" 
         src="/lib/plugins/dtree/images/bluefolders/plus3.gif" 
         style="padding: 0px; margin: 0px; width: 18px; height: 24px;">
         
         <img border="0" align="absmiddle" 
         src="/lib/plugins/dtree/images/skyblue/minus3.gif" 
         style="padding: 0px; margin: 0px; width: 18px; height: 24px;">  
         
         */
        foreach( $xml->attributes() as $key => $xval )
        {
            $val = ( string )$xval;
            if( $key == 'ns' )
            {
                if( $val == '.' )
                {
                    global $ID;
                    $parts = explode( ':', $ID );
                    global $conf;
                    $path = preg_replace( '|/+|', '/', 
                            $conf['savedir'] . '/pages/' );
                    while( count( $parts ) && !is_dir( 
                            $path . join( '/', $parts ) ) )
                    {
                        array_pop( $parts );
                    }
                    $data['ns'] = join( ':', $parts );
                }
                else
                {
                    $data['ns'] = $val;
                }
            }
            
            if( $key == 'skin' ) $data['skin'] = $val;
            if( $key == 'style' ) $data['style'] = $val;
            if( $key == 'class' ) $data['class'] = $val;
            
            if( $key == 'width' ) $data['width'] = $val;
            if( $key == 'height' ) $data['height'] = $val;
            
            if( $key == 'showhead' ) $data['showhead'] = $this->_bool( $val );
            
            if( $key == 'title' ) $data['title'] = $val;
            
            if( $key == 'nopages' ) $data['nopages'] = $this->_bool( $val );
            ;
            
            if( $key == 'openall' ) $data['openall'] = $this->_bool( $val );
            if( $key == 'openlevel' ) $data['openlevel'] = intval( $val );
        }
        
        if( isset( $xml->xns ) )
        {
            $data['xns'] = array();
            foreach( $xml->xns as $xns )
            {
                $data['xns'][] = ( string )$xns;
            }
        }
        if( isset( $xml->xpage ) )
        {
            $data['xpage'] = array();
            foreach( $xml->xpage as $xpage )
            {
                $data['xpage'][] = ( string )$xpage;
            }
        }
        if( isset( $xml->close ) )
        {
            $data['close'] = array();
            foreach( $xml->close as $close )
            {
                $data['close'][( string )$close] = ( string )$close;
            }
        }
        if( isset( $xml->open ) )
        {
            $data['open'] = array();
            foreach( $xml->open as $open )
            {
                $data['open'][( string )$open] = ( string )$open;
            }
        }
        if( isset( $xml->head ) )
        {
            $data['head'] = array();
            foreach( $xml->head as $head )
            {
                $data['head'][( string )$head] = ( string )$head;
            }
        }
        if( isset( $xml->tail ) )
        {
            $data['tail'] = array();
            foreach( $xml->tail as $tail )
            {
                $data['tail'][( string )$tail] = ( string )$tail;
            }
        }
        if( $data['ns'][0] == '.' )
        {
            $data['safe'] = false;
        }
        
        return $data;
    }

    /* -------------------------------------------------------------------------------- */
    function render($mode, &$renderer, $data)
    {
        global $conf, $ID, $INFO;
        
        $data = $this->_parseConfig( $data );
        
        if( !$data['safe'] ) return false;
        if( $data['error'] )
        {
            return true;
        }
        
//        $treerand = $data['idx'];
//        $this->cidx = $data['idx'];
        $treerand = $this->cidx = mt_rand( 1, 9999 );
        
        $this->head = array();
        $idx = 999;
        foreach( $data['head'] as $pt )
        {
            $pt = mb_strtolower( $pt );
            $this->head[$pt] = $idx;
            $this->head["$pt.txt"] = $idx;
            $idx--;
        }
        
        $this->tail = array();
        $idx = 999;
        foreach( $data['tail'] as $pt )
        {
            $pt = mb_strtolower( $pt );
            $this->tail[$pt] = $idx;
            $this->tail["$pt.txt"] = $idx;
            $idx--;
        }
        
        $this->added = array();
        $this->depth = array();
        if( !isset( $this->last_id ) ) $this->last_id = 1;
        
        $this->first_id = $this->current_item = 'd' . $this->last_id;
        
        $this->tree_tail = '';
        $this->xml_tree_data[$this->cidx] = '<?xml version="1.0" encoding="UTF-8"?><tree id="t0">' .
                 "\n";
        
        if( $data['showhead'] )
        {
            $title = $data['title'];
            if( !$title )
            {
                $title = p_get_first_heading( $data['ns'] . ':', true );
            }
            if( !$title )
            {
                $title = end( explode( ':', $data['ns'] ) );
            }
            if( !$title && $ns == '' )
            {
                $title = $conf['title'];
            }
            if( $title )
            {
                $this->xml_tree_data[$this->cidx] .= '<item text="' . $title .
                         '" id="d' . ($this->last_id) .
                         '" open="1" call="1" select="1">';
                $this->tree_tail = '</item>' . "\n";
                $startns = $data['ns'];
                if( !$startns ) $startns = $conf['start'];
                $this->added[$startns] = 'd' . ($this->last_id);
                $this->last_id++;
            }
        }
        $this->tree_tail .= '</tree>';
        
        $this->_recurse( $renderer, $data, 
                str_replace( ':', '/', $data['ns'] ) );
        
        $treevar = '_dtree_' . $treerand;
        $tree_data = '';
        $open = '';
        $close = '';
        
        $skin = $data['skin']; //$this->getConf( 'skin' );
        $treeimages = $treevar . '.setImagePath("'.DOKU_REL.'lib/plugins/dtree/images/' .
                 $skin . '/")';
        if( !$skin || !is_dir( DOKU_PLUGIN . "dtree/images/$skin/" ) )
        {
            if( $skin ) msg( sprintf( $this->getLang( 'badskin' ), $skin ), -1 );
            $treeimages = "$treevar.enableTreeImages(false);
            $treevar.enableTreeLines(false);
            $treevar.enableTextSigns(true);";
        }
        
        $parts = explode( ':', $INFO['id'] );
        while( count( $parts ) )
        {
            $path = join( ':', $parts );
            if( array_key_exists( $path, $this->added ) )
            {
                $this->current_item = $this->added[$path];
                break;
            }
            array_pop( $parts );
        }
        
        foreach( $this->added as $item => $id )
        {
            $item = ltrim( $item, ':' );
            $tree_data .= '"' . $id . '":"' . str_replace( ':', '/', $item ) .
                     '",';
            if( $this->current_item == $id ) continue;
            if( $data['openall'] )
            {
                $open .= $treevar . '.openItem("' . $id . '");';
            }
            elseif( $data['close'][$item] )
            {
                $close .= "$treevar.closeItem(\"$id\");";
            }
            elseif( $data['open'][$item] )
            {
                $open .= "$treevar.openItem(\"$id\");";
            }
            elseif( $data['openlevel'] && $this->depth[$item] <= $data['openlevel'] )
            {
                $open .= "$treevar.openItem(\"$id\");";
            }
        }
        $open .= $close;
        $open .= $treevar . '.openItem("' . $this->current_item . '");';
        
        $treeid = $treevar . '_id';
        $treexml = $treevar . '_xml';
        $treedata = $treevar . '_data';
        
        // TODO width/height handling?
        $width = $data['width'];
        $height = $data['height'];
        
        $style = $data['style'] ? ' style="' . $data['style'] . '"' : '';
        $class = $data['class'] ? ' ' . $data['class'] : '';
        $this->xml_tree_data[$this->cidx] = str_replace( "\n", '', 
                $this->xml_tree_data[$this->cidx] . $this->tree_tail );
        $this->xml_tree_data[$this->cidx] = str_replace( '></', "><'+'/", 
                $this->xml_tree_data[$this->cidx] );
        $DOKU_URL = DOKU_URL;
        
        $xmltreedata = $this->xml_tree_data[$this->cidx];
        $render_data = <<<JS
    <div id="$treeid"
    class="dtree_div$class"$style></div>
    <script type="text/javascript">
    var $treedata = { $tree_data"x":"x"};
    var $treevar = new dhtmlXTreeObject("$treeid", "100%", "100%", "t0");
    $treeimages;
    var $treexml = '$xmltreedata';
    $treevar.loadXMLString($treexml);
    $open
    $treevar.selectItem("$this->current_item");
    $treevar.setOnClickHandler(function(id)
    {
        if( $treedata [id] ) window.location = "$DOKU_URL" + $treedata [id];
    });
    </script>
JS;
        
        $renderer->doc .= $render_data;
        
        return true;
    }

    /* -------------------------------------------------------------------------------- */
    function _plog($s, $level = 0)
    {
        if( $f = @fopen( '/home/localhost/snippets.ato.su/log/dtree.log', 'a' ) )
        {
            for( $i = 0; $i < $level; $i++ )
                $s = " $s";
            fwrite( $f, "$s\n" );
            fclose( $f );
        }
    }

    function _usort($a, $b)
    {
        if( isset( $this->head[$a] ) && isset( $this->head[$b] ) )
        {
            return $this->head[$b] - $this->head[$a];
        }
        if( isset( $this->head[$a] ) ) return -$this->head[$a];
        if( isset( $this->head[$b] ) ) return $this->head[$b];
        
        if( isset( $this->head[$a] ) && isset( $this->head[$b] ) )
        {
            return $this->head[$a] - $this->head[$b];
        }
        if( isset( $this->tail[$a] ) ) return $this->tail[$a];
        if( isset( $this->tail[$b] ) ) return -$this->tail[$b];
        
        return strcmp( mb_strtolower( $a ), mb_strtolower( $b ) );
    }

    function _recurse(&$renderer, $data, $dir, $depth = 0)
    {
        $dir = trim( $dir, '/' );
        $dir = preg_replace( '|/+|', '/', $dir );
        $ns = str_replace( '/', ':', $dir );
        
        $mainPageId = $ns . ':';
        $mainPageExists;
        resolve_pageid( '', $mainPageId, $mainPageExists );
        if( !$mainPageExists ) $mainPageId = NULL;
        
        global $conf;
        $path = preg_replace( '|/+|', '/', 
                $conf['savedir'] . '/pages/' . $dir . '/' );
        
        $items = array();
        $dh = @opendir( $path );
        if( !$dh )
        {
            msg( sprintf( $this->getLang( 'dontexists' ), ($ns ? $ns : ':') ), 
                    -1 );
            return;
        }
        if( $dh )
        {
            while( false !== ($item = readdir( $dh )) )
            {
                if( $item[0] == '.' || $item[0] == '_' ) continue;
                $items[] = $item;
            }
            closedir( $dh );
        }
        usort( $items, array("syntax_plugin_dtree","_usort" 
        ) );
        
        foreach( $items as $item )
        {
            $name = str_replace( '.txt', '', $item );
            
            $id = $ns;
            if( $ns != '' ) $id .= ':';
            $id .= $name;
            
            $infos = array('id' => $id,'name' => $name 
            );
            
            if( is_dir( $path . '/' . $item ) )
            {
                if( $this->_isExcluded( $infos, $data['xns'] ) ) continue;
                if( $this->_already( $id, $depth ) ) continue;
                
                $startid = $id . ':';
                $startexist = false;
                resolve_pageid( '', $startid, $startexist );
                $infos['title'] = ($startexist) ? p_get_first_heading( 
                        $startid, true ) : $name;
                
                $this->_displayNSBegin( $renderer, $infos );
                $newdir = $dir;
                if( $dir != '' ) $newdir .= '/';
                $newdir .= $item;
                $newns = $ns;
                if( $ns != '' ) $newns .= ':';
                $newns .= $item;
                //                $this->_recurse($renderer, $data, $newdir, $newns, $depth + 1);
                $this->_recurse( $renderer, $data, $newdir, $depth + 1 );
                $this->_displayNSEnd( $renderer );
            }
            else
            {
                if( $data['nopages'] ) continue;
                if( substr( $item, -4 ) != ".txt" ) continue;
                if( $this->_isExcluded( $infos, $data['xpage'] ) ) continue;
                if( $this->_already( $id, $depth ) ) continue;
                $infos['title'] = p_get_first_heading( $id, true );
                if( is_null( $infos['title'] ) ) $infos['title'] = $name;
                if( $id != $mainPageId ) $this->_displayPage( $renderer, 
                        $infos );
            }
        }
    }

    /* -------------------------------------------------------------------------------- */
    function _already($id, $depth)
    {
        if( isset( $this->added[$id] ) )
        {
            return true;
        }
        $this->added[$id] = 'd' . (intval( $this->last_id ));
        $this->depth[$id] = $depth;
        return false;
    }

    /* -------------------------------------------------------------------------------- */
    function _itemtext($item)
    {
        $href = DOKU_URL . ltrim( (str_replace( ':', '/', $item['id'] )), '/' );
        return htmlspecialchars( $item['title'] ) . ' &lt;a href=&quot;' . $href .
                 '&quot;&gt;»&lt;/a&gt;';
    }

    /* -------------------------------------------------------------------------------- */
    function _displayNSBegin(&$renderer, $item)
    {
        $this->xml_tree_data[$this->cidx] .= '<item id="d' . $this->last_id .
                 '" text="' . $this->_itemtext( $item ) . '">';
        $this->last_id++;
    }

    /* -------------------------------------------------------------------------------- */
    function _displayNSEnd(&$renderer)
    {
        $this->xml_tree_data[$this->cidx] .= '</item>' . "\n";
    }

    /* -------------------------------------------------------------------------------- */
    function _displayPage(&$renderer, $item)
    {
        $this->xml_tree_data[$this->cidx] .= '<item id="d' . $this->last_id .
                 '" text="' . $this->_itemtext( $item ) . '"';
        $this->xml_tree_data[$this->cidx] .= '></item>' . "\n";
        $this->last_id++;
    }
}
