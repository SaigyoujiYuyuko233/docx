<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 18:49
 */
namespace Docx;
/**
 * Class Node
 * @package Docx
 */
class Node {
    /**
     * @var null | \DOMElement
     */
    private $_domElement = null;
    /**
     * @var null | Docx
     */
   private $_docx = null;
    /**
     * Node constructor.
     * @param $docx Docx
     * @param $domElement \DOMElement
     * @param bool $isDirect ( Are we inside a table or similar? we may need to process differently if so)
     * @param string | null $parentNodeId ( Tracks our parent div)
     */
   public function __construct($docx, $domElement,  $isDirect = false, $parentNodeId = null){
       $this->_docx = $docx;
       $this->_domElement = $domElement;
       $this->_parseNode($isDirect);
       $this->id = $this->_docx->generateNodeId();
       $this->isDirect = $isDirect;
       $this->parentId = $parentNodeId;
       $this->type = $domElement->nodeName;
   }

    /**
     * @desc Integrates this Node object, into the Docx
     * @param $docx Docx
     */
   public function attachToDocx($docx){
       unset ( $this->_docx);
       $docx->attachNode($this ) ;
   }

    /**
     * @var null | int
     * @desc Internal NodeId of the parent table (if any )
     *
     */
   private $_tableId = null ;
    /**
     * @var null | Style
     * @desc Tracks the discovered word style of the given node
     */
   private $_wordStyle = null;
    /**
     * @var array
     * @desc Track internal run objects
     */
   protected $_run = [] ;

    /**
     * @param null DomElement $domElement
     * @return Style
     */
   private function _getStyle($domElement = null ) {
       if ($domElement == null ) $domElement = $this->_domElement;
       $styleQuery = $this->_docx->getXPath()->query("w:pPr/w:pStyle", $domElement);
       $style = '';
       if ($styleQuery->length != 0)
           $style = $styleQuery->item(0)->getAttribute('w:val');
       return Style::getFromStyleName($style) ;
   }

    /**
     * @param bool $isDirect
     */
   private function _parseNode($isDirect = false ) {
       $wordStyle = $this->_getStyle( ) ;
       $styleInfo = null ; #@TODO - integrate all calls into Style object
   //    $wordStyle = $this->findStyle($this->dom);
   //    $styleInfo = Style::getStyleObject($wordStyle, $this->docx);
       $this->_wordStyle = $wordStyle;

       /*
        * If this node is NOT a drawing container, AND we're not a direct node parse within w:body,
        * don't perform further parsing
        */
       if (
           (
               !($this->_domElement->parentNode->nodeName == 'w:r' && $this->_domElement->nodeName == 'w:drawing')
           ) && (
               $this->_domElement->parentNode->nodeName != 'w:body' && !$isDirect
           )
       ){
           return;
       }

       /*
        * Override / assign ->_tableID
        */
       if (!$isDirect) $this->_tableId = null;

       /*
        * Process each type of node
        */
       switch ($this->_domElement->nodeName){
           case 'w:p':
               $isListItem = false;
               $listLevel = 0;
               $indent = null;

               # Get the list level using the openXml format
               $listQuery = $this->_docx->getXPath()->query("w:pPr/w:numPr/w:ilvl", $this->_domElement);
               if ($listQuery->length > 0){
                   $listLevel = (int) $listQuery->item(0)->getAttribute('w:val') + 1;
               }

               # If the style list info is NOT 0, then override the openXml iteration
               if (is_object($styleInfo)){
                   if ($styleInfo->listLevel > 0) $listLevel = $styleInfo->listLevel;
               }

               # Run through text runs & hyperlinks
               foreach ($this->_domElement->childNodes as $childNode){
                   $nodeName = $childNode->nodeName;
                   switch ($nodeName){
                       case 'w:r':
                       case 'w:hyperlink':
                           $this->_run[] = new Run($childNode, $this );
                       break;
                   }
               }

               # Get the indentation
               $indentQuery = $this->_docx->getXPath()->query("w:pPr/w:ind", $this->_domElement);
               if ($indentQuery->length > 0){
                   $firstLineInd = $indentQuery->item(0)->getAttribute('w:firstLine');
                   $indent = (int) $this->_docx->twipToPt($firstLineInd);
               }

               $this->indent = $indent;
               $this->listLevel = $listLevel;
               break;
           case 'w:drawing':
         //      $this->img = $this->loadDrawing($this->dom);
               break;
           case 'w:txbxContent':

               break;
           case 'w:tbl':
         //      $this->_tableId = $this->id;
         //      $this->createTableGrid($this->dom);
               break;
       }

   }
}