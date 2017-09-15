<?php

namespace model;

final class SimpleDaosDAO extends MysqliDAO {
    public function obtenerArrayEstructuralParaTo(string $toClass) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $array = $this->obtenerArrayEstructuralParaToNoTransaction($toClass);
        static::$link->commit();
        return $array;
    }

    private function obtenerArrayEstructuralParaToNoTransaction($toClass) {
        $array = [];
        $stmt = static::$link->prepare(
            "SELECT tipo FROM tnr_api_ent_simpleDaos WHERE transferObject=?");
        $stmt->bind_param('s', $toClass);
        $stmt->execute();
        $stmt->bind_result($tipo);
        $done = $stmt->fetch();
        $stmt->close();
        if($done) {
            $array['to'] = $toClass;
            $array['tipo'] = $tipo;
            $array['toToTables'] = $this->getToToTables($toClass);
            if($tipo === 'wrapper') {
                $array['others'] = $this->getChildren($toClass); // Recursiva
            }
        }
        return $array;
    }

    private function getToToTables($toClass) {
        $array = [];
        $stmt = static::$link->prepare(
            "SELECT id, tabla FROM tnr_api_ent_toToTableS WHERE simpleDao=?");
        $stmt->bind_param('s', $toClass);
        $stmt->execute();
        $stmt->bind_result($id, $table);
        while($stmt->fetch()) {
            $array[] = ['id'=>$id, 'tabla'=>$table];
        }
        $stmt->close();

        foreach($array as &$item) {
            $id = $item['id'];
            $stmt = static::$link->prepare(
                "SELECT labelPropiedad, campoEnLaTabla FROM tnr_api_ent_toToTableEntries WHERE toToTableS_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($label, $campo);
            $item['campos'] = [];
            while ($stmt->fetch()) {
                $item['campos'][$label] = $campo;
            }
            $stmt->close();
        }

        return $array;
    }

    private function getChildren($toClass) {
        $array = [];
        $stmt = static::$link->prepare(
            "SELECT child FROM tnr_api_rel_wrappedDaos WHERE parent=?");
        $stmt->bind_param('s', $toClass);
        $stmt->execute();
        $stmt->bind_result($child);
        $children = [];
        while($stmt->fetch()) {
            $children[] = $child;
        }
        $stmt->close();

        foreach($children as $child) {
            $array[] = $this->obtenerArrayEstructuralParaToNoTransaction($child);
        }

        return $array;
    }
}