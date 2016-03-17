<?php
/**
 * Created by PhpStorm.
 * User: jaskolek
 * Date: 2015-02-24
 * Time: 18:45
 */

namespace Jaskolek;


class Utils
{
    /**
     * @param $data
     * @param string $path
     * @param string $type
     * @return \PHPExcel
     * @throws \PHPExcel_Exception
     */
    public static function getExcel($data, $path = "data.xlsx", $type = "Excel2007")
    {

        $colMaxSizes = [];
        //get keys
        $keys = [];
        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                $keys[$key] = true;
            }
        }
        $keys = array_keys($keys);

        foreach ($keys as $i => $key) {
            $colMaxSizes[$i] = strlen($key);
        }
        //update data
        $newData = [];
        foreach ($data as $row) {
            $newRow = [];
            foreach ($keys as $i => $key) {
                $newRow[$i] = @$row[$key];
                $colMaxSizes[$i] = max(strlen($row[$key]), $colMaxSizes[$i]);
            }
            $newData[] = $newRow;
        }

        $excel = new \PHPExcel();
        $excel->getActiveSheet()->fromArray($keys, null, 'A1', true);
//        $excel->getActiveSheet()->fromArray($newData, null, 'A2', true);
        foreach($newData as $key => $row) {
            $excel->getActiveSheet()->fromArray($row, null, 'A' . ($key + 2), true);
            echo $key . "/" . count($newData) . "\n";
        }
        foreach ($colMaxSizes as $key => $value) {
            $size = min(30, $value) + 4;
            $excel->getActiveSheet()->getColumnDimensionByColumn($key)->setWidth($size);
            $excel->getActiveSheet()->getStyleByColumnAndRow($key, 1)->applyFromArray([
                'fill' => [
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => ['rgb' => 'bbbbbb']
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '000000']
                ],
                'alignment' => [
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                ],
            ]);
        }


        return $excel;
    }
    public static function writeData($data, $path = "data.xlsx", $type = "Excel2007")
    {
        $excel = self::getExcel($data, $path, $type);
        $writer = \PHPExcel_IOFactory::createWriter($excel, $type);
        $writer->save($path);
    }
}