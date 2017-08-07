<?php
namespace inopx\io;

/**
 *
 * @author INOVUM Tomasz Zadora
 */
class IOTool {
  
  public static function dataToBase64($data) {
    return base64_encode(serialize($data));
  }
  
  public static function dataFromBase64($base64) {
    return unserialize(base64_decode($base64));
  }
  
  public static function packObjectForStorage($obj) {
    
    return \base64_encode(\gzcompress(\serialize($obj)));
    
  }
  
  public static function unpackObjectFromStorage($objData) {
    
    return \unserialize(\gzuncompress(\base64_decode($objData)));
    
  }
  
  public static function sanitizeFilename($txt) {
    
    return mb_ereg_replace("([\.]{2,})", '',  mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $txt)  );
    
  }
  
  public static function getClusteredDir($fileId, $createDirs = false, $path = "", $maxFiles = 100, $maxDirs = 10, $chmod = 0775)
    {
        $rt = "";
        
        // Conversion to number
        if (!is_numeric($fileId)) {
            
            $fileId = crc32($fileId);
            
        }
        
        if ($createDirs) {
            if (!file_exists($path))
            {
                if (!mkdir($path, $chmod)) { 
                  return false;
                }
                
                @chmod($path, $chmod);
            }
        }

        if ( $fileId <= 0 || $fileId <= $maxFiles ) { 
          return '';
        }

        // Rest from dividing fileId / maxFiles
        $restId = $fileId%$maxFiles;

        $formattedFileId = $fileId - $restId;

        // Number of catalogs needed to place the file
        $howMuchDirs = $formattedFileId / $maxFiles;

        while ($howMuchDirs > $maxDirs) {
          
            $r = $howMuchDirs%$maxDirs;
            $howMuchDirs -= $r;
            $howMuchDirs = $howMuchDirs/$maxDirs;
            $rt .= $r . \DIRECTORY_SEPARATOR; // DIRECTORY_SEPARATOR

            if ($createDirs) {
              
                $prt = $path.$rt;
                if (!file_exists($prt)) {
                    mkdir($prt);
                    @chmod($prt, $chmod);
                }
            }
        }

        $rt .= $howMuchDirs-1;
        if ($createDirs) {
            $prt = $path.$rt;
            if (!file_exists($prt)) {
                mkdir($prt);
                @chmod($prt, $chmod);
            }
        }

        $rt .= \DIRECTORY_SEPARATOR;	// DIRECTORY_SEPARATOR

        return $rt;
    }
  
}
