<?php
namespace inopx\cache;

/**
 * Description of AdapaterInterfaceInputOutput
 *
 * @author INOVUM Tomasz Zadora
 */
class AdapaterInterfaceInputOutput implements InterfaceInputOutput {
  
  public function &input($dataForStorage) {
    
    return \inopx\io\IOTool::dataToBase64($dataForStorage);
    
  }

  public function &output($dataFromStorage) {
    
    return \inopx\io\IOTool::dataFromBase64($dataFromStorage);
    
  }

  
}
