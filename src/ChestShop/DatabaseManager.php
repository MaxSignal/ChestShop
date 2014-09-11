<?php

namespace ChestShop;


class DatabaseManager
{
    private $database;

    public function __construct($path)
    {
        $this->database = new \SQLite3($path);
        $sql = "CREATE TABLE IF NOT EXISTS ChestShop(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    shopOwner TEXT NOT NULL,
                    saleNum INTEGER NOT NULL,
                    price INTEGER NOT NULL,
                    productID INTEGER NOT NULL,
                    productMeta INTEGER NOT NULL,
                    signX INTEGER NOT NULL,
                    signY INTEGER NOT NULL,
                    signZ INTEGER NOT NULL,
                    chestX INTEGER NOT NULL,
                    chestY INTEGER NOT NULL,
                    chestZ INTEGER NOT NULL
		)";
        $this->database->exec($sql);
    }

    public function registerShop($shopOwner, $saleNum, $price, $productID, $productMeta, $sign, $chest)
    {
        $this->database->exec("INSERT INTO ChestShop (shopOwner, saleNum, price, productID, productMeta, signX, signY, signZ, chestX, chestY, chestZ) VALUES ('$shopOwner', $saleNum, $price, $productID, $productMeta, $sign->x, $sign->y, $sign->z, $chest->x, $chest->y, $chest->z)");
    }

    public function selectByCondition(array $condition)
    {
        $where = "";
        foreach ($condition as $key => $val) {
            $where .= "$key = $val";
            $where .= " ";
        }
        return $this->database->query("SELECT * FROM ChestShop WHERE $where")->fetchArray(SQLITE3_ASSOC);
    }

    public function deleteByCondition(array $condition)
    {
        $where = "";
        foreach ($condition as $key => $val) {
            $where .= "$key = $val";
            $where .= " ";
        }
        $this->database->exec("DELETE FROM ChestShop WHERE $where");
    }
} 