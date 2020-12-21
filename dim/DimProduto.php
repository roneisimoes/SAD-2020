<?php
namespace dimensoes;
mysqli_report(MYSQLI_REPORT_STRICT);

$separador = DIRECTORY_SEPARATOR;
$root = $_SERVER['DOCUMENT_ROOT'];

require_once($root.'/etl-2020/models/Produto.php');
require_once('Sumario.php');
use dimensoes\Sumario;
use models\Produto;

class DimProduto{
   public function carregarDimProduto(){
      $dataAtual = date('Y-m-d');
      $sumario = new Sumario();
      try{
         $connDimensao = $this->conectarBanco('dm_comercial');
         $connComercial = $this->conectarBanco('bd_comercial');
      }catch(\Exception $e){
         die($e->getMessage());
      }
      $sqlDim = $connDimensao->prepare('select SK_produto, codigo, nome, unidade_medida, valor_unitario
                                        from dim_produto');
      $sqlDim->execute();
      $resultDim = $sqlDim->get_result();

      if($resultDim->num_rows === 0){//A dimensão está vazia
         $sqlComercial = $connComercial->prepare("select * from produto"); //Cria variável com comando SQL
         $sqlComercial->execute(); //Executa o comando SQL
         $resultComercial = $sqlComercial->get_result(); //Atribui à variával o resultado da consulta

         if($resultComercial->num_rows !== 0){ //Testa se a consulta retornou dados
            while($linhaProduto = $resultComercial->fetch_assoc()){ //Atibui à variável cada linha até o último
               $produto = new Produto();
               $produto->setProduto($linhaProduto['codigo'], $linhaProduto['nome_produto'], $linhaProduto['unid_medida'],
                                    $linhaProduto['preco']);

               $slqInsertDim = $connDimensao->prepare("insert into dim_produto
                                                      (codigo, nome, unidade_medida, valor_unitario, data_ini)
                                                      values
                                                      (?,?,?,?,?)");
               $slqInsertDim->bind_param("issss", $produto->codigo, $produto->nome, $produto->unidadeMedida,
                                          $produto->valorUnitario, $dataAtual);
               $slqInsertDim->execute();
               $sumario->setQuantidadeInclusoes();
            }
            $sqlComercial->close();
            $sqlDim->close();
            $slqInsertDim->close();
            $connComercial->close();
            $connDimensao->close();
         }
      }else{//A dimensão já contém dados
         $sqlComercial = $connComercial->prepare('select*from produto');
         $sqlComercial->execute();
         $resultComercial = $sqlComercial->get_result();

         while($linhaComercial = $resultComercial->fetch_assoc()){
            $sqlDim = $connDimensao->prepare('select SK_produto, codigo, nome, unidade_medida, valor_unitario
                                             from dim_produto where codigo = ? and data_fim is null');
            $sqlDim->bind_param("s", $linhaComercial['codigo']);
            $sqlDim->execute();

            $resultDim = $sqlDim->get_result();
            if($resultDim->num_rows === 0){//O cliente na comercial não está na dimensional
               $slqInsertDim = $connDimensao->prepare("insert into dim_produto
                                                      (codigo, nome, unidade_medida, valor_unitario, data_ini)
                                                      values
                                                      (?,?,?,?,?)");echo "aaa";
               $slqInsertDim->bind_param("issss", $linhaComercial['codigo'],$linhaComercial['nome_produto'], $linhaComercial['unid_medida'],
                                          $linhaComercial['preco'], $dataAtual);
               $slqInsertDim->execute();

               $sumario->setQuantidadeInclusoes();
            }else{//O cliente comercial já está na dimensional
               $strComTeste = $linhaComercial['codigo'].$linhaComercial['nome_produto'].$linhaComercial['unid_medida'].
                                             $linhaComercial['preco'];
               $linhaDim = $resultDim->fetch_assoc();
               $strDimTeste = $linhaDim['codigo'].$linhaDim['nome'].$linhaDim['unidade_medida'].$linhaDim['valor_unitario'];

               if(!$this->strIgual($strComTeste, $strDimTeste)){ //Registros não são iguais. Houve alteração
                  $skProduto = $linhaDim['SK_produto'];
                  $sqlUpdateDim = $connDimensao->prepare("UPDATE dim_produto SET
                                                            data_fim = ?
                                                            WHERE
                                                            SK_produto = ?");
                  $sqlUpdateDim->bind_param("si", $dataAtual, $skProduto);
                  $sqlUpdateDim->execute();
                  if(!$sqlUpdateDim->error){
                     $slqInsertDim = $connDimensao->prepare("insert into dim_produto
                                                      (codigo, nome, unidade_medida, valor_unitario, data_ini)
                                                      values
                                                      (?,?,?,?,?)");
                     $slqInsertDim->bind_param("issss", $linhaComercial['codigo'],$linhaComercial['nome_produto'], $linhaComercial['unid_medida'],
                                                $linhaComercial['preco'], $dataAtual);
                     $slqInsertDim->execute();
                     $sumario->setQuantidadeAlteracoes();
                  }else{
                     throw new \Exception("Erro ao atualizar dimensão");
                  }
               }
            }
         }
      }
      return $sumario;
   }
   private function strIgual($strAtual, $strNova){
      $hashAtual = md5($strAtual);
      $hashNovo = md5($strNova);
      if($hashAtual === $hashNovo){
         return TRUE;
      }else{
         return FALSE;
      }
   }
   private function conectarBanco($banco){
      if(!defined('DS')){
         define('DS', DIRECTORY_SEPARATOR);
      }
      if(!defined('BASE_DIR')){
         define('BASE_DIR', dirname(__FILE__).DS);
      }
      require(BASE_DIR.'config_db.php');

      try{
         $conn = new \MySQLi($dbhost, $user, $password, $banco);
         return $conn;
      }catch(mysqli_sql_exception $e){
         throw new \Exception($e);
         die;
      }
   }
}
?>