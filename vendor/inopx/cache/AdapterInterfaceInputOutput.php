<?php
namespace inopx\cache;

/**
 * Default adapter for input-output transformation between PHP and storage.
 *
 * @author INOVUM Tomasz Zadora
 */
class AdapterInterfaceInputOutput implements InterfaceInputOutput {
  
  public function input($dataForStorage) {
    
    return \inopx\io\IOTool::dataToBase64($dataForStorage);
    
  }

  public function output($dataFromStorage) {
    
    return \inopx\io\IOTool::dataFromBase64($dataFromStorage);
    
  }

  
}
