<?php
namespace App\Utils;

class FileUpload {
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;

    public function __construct($uploadPath = 'public/uploads/') {
        $this->uploadPath = $uploadPath;
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxSize = 5 * 1024 * 1024; // 5MB
    }

    public function upload($file, $subfolder = '') {
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
        $filename = uniqid() . '.' . $extension;
        $fullPath = $this->uploadPath . $subfolder . '/';
        
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $destination = $fullPath . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $subfolder . '/' . $filename;
        }

        throw new \Exception('Failed to upload file');
    }

    public function delete($filepath) {
        $fullPath = $this->uploadPath . $filepath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
}