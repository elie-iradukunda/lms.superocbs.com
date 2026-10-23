<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\FuelOrders;

/** The station (or whoever was there) confirms the litres actually given (section 11). */
final class FuelOrderController
{
    public function confirmForm(array $params): void
    {
        $order = $this->load($params);
        \view('fuel/confirm', ['title' => 'Confirm litres given', 'order' => $order]);
    }

    public function confirm(array $params): void
    {
        $order = $this->load($params);
        try {
            $litres = (float) str_replace(',', '', (string) ($_POST['litres'] ?? ''));
            $price = trim((string) ($_POST['unit_price'] ?? ''));
            FuelOrders::confirmDelivered((int) $order['id'], $litres, $price === '' ? null : (float) str_replace(',', '', $price), (string) ($_POST['note'] ?? ''));
            Flash::success('Litres given recorded.');
            $this->redirect(\url(['fuel_orders', $order['id']]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['fuel_orders', $order['id'], 'confirm']));
        }
    }

    private function load(array $params): array
    {
        if (\Models\LogisticsData::find('fuel_orders', (int) ($params['id'] ?? 0), \current_context()) === null) {
            Flash::error('That fuel order does not exist.');
            $this->redirect(\url('fuel_orders'));
        }
        $statement = Database::connection()->prepare(
            'SELECT f.*, v.plate_number, s.supplier_name, t.reference_code AS trip_code
               FROM fuel_orders f INNER JOIN vehicles v ON v.id = f.vehicle_id LEFT JOIN suppliers s ON s.id = f.supplier_id LEFT JOIN trips t ON t.id = f.trip_id
              WHERE f.id = ?'
        );
        $statement->execute([(int) $params['id']]);

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
