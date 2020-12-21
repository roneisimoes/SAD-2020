<?php
namespace models;
class Produto{
   public $codigo;
   public $nome;
   public $unidadeMedida;
   public $valorUnitario;

   public function setProduto($codigo, $nome, $unidadeMedida, $valorUnitario){
      $this->codigo = $codigo;
      $this->nome = $nome;
      $this->unidadeMedida =$unidadeMedida;
      $this->valorUnitario = $valorUnitario;
   }
}
?>