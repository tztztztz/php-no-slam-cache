<?php
namespace inopx\cache;

/**
 * Interface for transforming data before / after insterting / retrieving from storage.
 * 
 * @author INOVUM Tomasz Zadora
 */
interface InterfaceInputOutput {
  
  /**
   * Function to transform data before putting into storage
   */
  public function input($dataForStorage);
  
  /**
   * Function to transform data after retrieving from storage
   */
  public function output($dataFromStorage);
  
  
}
