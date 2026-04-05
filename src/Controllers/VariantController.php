<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VariantController
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * Helper para obtener datos del request
     */
    private function getRequestData(Request $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            $body = $request->getBody()->getContents();
            if (!empty($body)) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
        }

        $parsedBody = $request->getParsedBody();
        return is_array($parsedBody) ? $parsedBody : [];
    }

    /**
     * GET /api/admin/products/{id}/variants - Obtener variantes de un producto
     */
    public function getByProduct(Request $request, Response $response, array $args): Response
    {
        $productId = $args['id'];

        // Verificar que el producto existe
        $check = $this->db->prepare("SELECT id FROM products WHERE id = ?");
        $check->execute([$productId]);
        if (!$check->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Producto no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Obtener variantes con sus atributos
        $sql = "SELECT pv.id, pv.product_id, pv.sku_suffix, pv.price_adjustment, pv.stock,
                       pv.is_active, pv.sort_order,
                       pva.attribute_id, pva.attribute_value_id,
                       pa.name as attribute_name, pa.display_name, pa.type as attribute_type,
                       pav.value as attribute_value, pav.color_hex
                FROM product_variants pv
                LEFT JOIN product_variant_attributes pva ON pva.variant_id = pv.id
                LEFT JOIN product_attributes pa ON pa.id = pva.attribute_id
                LEFT JOIN product_attribute_values pav ON pav.id = pva.attribute_value_id
                WHERE pv.product_id = ?
                ORDER BY pv.sort_order ASC, pv.id ASC, pa.sort_order ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll();

        // Agrupar por variante
        $variants = [];
        foreach ($rows as $row) {
            $vid = $row['id'];
            if (!isset($variants[$vid])) {
                $variants[$vid] = [
                    'id' => (int)$row['id'],
                    'product_id' => (int)$row['product_id'],
                    'sku_suffix' => $row['sku_suffix'],
                    'price_adjustment' => $row['price_adjustment'],
                    'stock' => (int)$row['stock'],
                    'is_active' => (bool)$row['is_active'],
                    'sort_order' => (int)$row['sort_order'],
                    'attributes' => []
                ];
            }
            if ($row['attribute_id']) {
                $variants[$vid]['attributes'][] = [
                    'attribute_id' => (int)$row['attribute_id'],
                    'attribute_value_id' => (int)$row['attribute_value_id'],
                    'attribute_name' => $row['attribute_name'],
                    'display_name' => $row['display_name'],
                    'attribute_type' => $row['attribute_type'],
                    'value' => $row['attribute_value'],
                    'color_hex' => $row['color_hex']
                ];
            }
        }

        $response->getBody()->write(json_encode(array_values($variants)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/admin/products/{id}/variants - Guardar variantes (reemplaza todas)
     */
    public function saveVariants(Request $request, Response $response, array $args): Response
    {
        $productId = $args['id'];
        $data = $this->getRequestData($request);

        // Verificar que el producto existe
        $check = $this->db->prepare("SELECT id FROM products WHERE id = ?");
        $check->execute([$productId]);
        if (!$check->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Producto no encontrado']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $variants = $data['variants'] ?? [];

        try {
            $this->db->beginTransaction();

            // Eliminar variantes existentes (CASCADE elimina variant_attributes)
            $deleteAttrs = $this->db->prepare("DELETE FROM product_variant_attributes WHERE variant_id IN (SELECT id FROM product_variants WHERE product_id = ?)");
            $deleteAttrs->execute([$productId]);

            $deleteVariants = $this->db->prepare("DELETE FROM product_variants WHERE product_id = ?");
            $deleteVariants->execute([$productId]);

            // Insertar nuevas variantes
            $insertVariant = $this->db->prepare(
                "INSERT INTO product_variants (product_id, sku_suffix, price_adjustment, stock, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $insertAttr = $this->db->prepare(
                "INSERT INTO product_variant_attributes (variant_id, attribute_id, attribute_value_id)
                 VALUES (?, ?, ?)"
            );

            $savedCount = 0;
            foreach ($variants as $index => $variant) {
                $insertVariant->execute([
                    $productId,
                    $variant['sku_suffix'] ?? null,
                    floatval($variant['price_adjustment'] ?? 0),
                    intval($variant['stock'] ?? 0),
                    isset($variant['is_active']) ? ($variant['is_active'] ? 1 : 0) : 1,
                    intval($variant['sort_order'] ?? $index)
                ]);

                $variantId = $this->db->lastInsertId();

                // Insertar atributos de la variante
                if (isset($variant['attributes']) && is_array($variant['attributes'])) {
                    foreach ($variant['attributes'] as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            $insertAttr->execute([
                                $variantId,
                                (int)$attr['attribute_id'],
                                (int)$attr['attribute_value_id']
                            ]);
                        }
                    }
                }

                $savedCount++;
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'message' => 'Variantes guardadas correctamente',
                'variants_saved' => $savedCount
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error saving variants: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Error al guardar variantes: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * DELETE /api/admin/products/{id}/variants/{variantId} - Eliminar una variante
     */
    public function deleteVariant(Request $request, Response $response, array $args): Response
    {
        $variantId = $args['variantId'];

        try {
            $this->db->beginTransaction();

            $this->db->prepare("DELETE FROM product_variant_attributes WHERE variant_id = ?")->execute([$variantId]);
            $this->db->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$variantId]);

            $this->db->commit();

            $response->getBody()->write(json_encode(['message' => 'Variante eliminada correctamente']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Error al eliminar variante']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
