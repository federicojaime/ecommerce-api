<?php
namespace App\Utils;

use Psr\Http\Message\UploadedFileInterface;

class FileUpload {
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;
    private $baseUrl;

    public function __construct($uploadPath = null) {
        // Obtener la ruta base del proyecto
        $projectRoot = dirname(dirname(__DIR__));
        $this->uploadPath = $uploadPath ?? $projectRoot . '/public/uploads/';
        $this->uploadPath = rtrim($this->uploadPath, '/') . '/';
        
        // URL base para acceder a las imágenes
        $this->baseUrl = '/ecommerce-api/public/uploads/';
        
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxSize = 10 * 1024 * 1024; // 10MB
        
        // Crear directorio si no existe
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Subir archivo usando PSR-7 UploadedFileInterface
     */
    public function uploadFile(UploadedFileInterface $uploadedFile, $subfolder = ''): string {
        // Verificar errores de upload
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new \Exception('Upload error: ' . $this->getUploadErrorMessage($uploadedFile->getError()));
        }

        // Verificar tamaño
        if ($uploadedFile->getSize() > $this->maxSize) {
            throw new \Exception('File size too large. Maximum allowed: ' . ($this->maxSize / 1024 / 1024) . 'MB');
        }

        // Verificar tipo MIME
        $clientMediaType = $uploadedFile->getClientMediaType();
        if (!in_array($clientMediaType, $this->allowedTypes)) {
            throw new \Exception('File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes));
        }

        // Obtener extensión del archivo
        $clientFilename = $uploadedFile->getClientFilename();
        $extension = pathinfo($clientFilename, PATHINFO_EXTENSION);
        
        // Validar extensión
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            throw new \Exception('File extension not allowed');
        }

        // Generar nombre único
        $filename = $this->generateUniqueFilename($extension);
        
        // Crear directorio de destino
        $targetDir = $this->uploadPath;
        if (!empty($subfolder)) {
            $targetDir .= rtrim($subfolder, '/') . '/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }

        $targetPath = $targetDir . $filename;
        
        // Log para debug
        error_log("Uploading file to: " . $targetPath);
        error_log("Target dir exists: " . (is_dir($targetDir) ? 'YES' : 'NO'));
        error_log("Target dir writable: " . (is_writable($targetDir) ? 'YES' : 'NO'));
        
        // Mover archivo
        $uploadedFile->moveTo($targetPath);
        
        // Verificar que se subió correctamente
        if (!file_exists($targetPath)) {
            throw new \Exception('Failed to save uploaded file');
        }
        
        // Retornar ruta relativa para la base de datos
        $relativePath = $subfolder ? $subfolder . '/' . $filename : $filename;
        error_log("File uploaded successfully. Relative path: " . $relativePath);
        
        return ltrim($relativePath, '/');
    }

    /**
     * Obtener URL completa de una imagen
     */
    public function getImageUrl($relativePath): string {
        return $this->baseUrl . ltrim($relativePath, '/');
    }

    /**
     * Método legacy para compatibilidad con arrays $_FILES
     */
    public function upload($file, $subfolder = ''): string {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('No valid file uploaded');
        }

        if ($file['size'] > $this->maxSize) {
            throw new \Exception('File size too large');
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new \Exception('File type not allowed');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $this->generateUniqueFilename($extension);
        $fullPath = $this->uploadPath . $subfolder . '/';
        
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $destination = $fullPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ltrim($subfolder . '/' . $filename, '/');
        }

        throw new \Exception('Failed to upload file');
    }

    /**
     * Eliminar archivo
     */
    public function deleteFile($filepath): bool {
        $fullPath = $this->uploadPath . ltrim($filepath, '/');
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Generar nombre único para archivo
     */
    private function generateUniqueFilename($extension): string {
        return uniqid('img_', true) . '.' . strtolower($extension);
    }

    /**
     * Obtener mensaje de error de upload
     */
    private function getUploadErrorMessage($errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Obtener información del archivo
     */
    public function getFileInfo($filepath): ?array {
        $fullPath = $this->uploadPath . ltrim($filepath, '/');
        if (!file_exists($fullPath)) {
            return null;
        }

        return [
            'size' => filesize($fullPath),
            'mime_type' => mime_content_type($fullPath),
            'modified' => filemtime($fullPath),
            'path' => $fullPath
        ];
    }

    /**
     * Validar si un archivo es una imagen válida
     */
    public function validateImage($filepath): bool {
        $fullPath = $this->uploadPath . ltrim($filepath, '/');
        
        if (!file_exists($fullPath)) {
            return false;
        }

        $imageInfo = getimagesize($fullPath);
        return $imageInfo !== false;
    }

    /**
     * Obtener ruta completa del archivo
     */
    public function getFullPath($relativePath): string {
        return $this->uploadPath . ltrim($relativePath, '/');
    }
}