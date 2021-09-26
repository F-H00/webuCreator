<?php declare(strict_types=1);

namespace spawn\system\Core\Base\Database\Definition;


use Doctrine\DBAL\Exception;
use spawn\system\Core\Base\Database\DatabaseConnection;
use spawn\system\Core\Base\Database\Definition\TableDefinition\AbstractTable;
use spawn\system\Core\Helper\UUID;
use spawn\system\Throwables\WrongEntityForRepositoryException;

abstract class TableRepository {

    protected array $tableColumns = [];
    protected string $tableName;

    abstract public static function getEntityClass() : string;


    public function __construct(
        AbstractTable $tableDefinition
    )
    {
        foreach($tableDefinition->getTableColumns() as $tableColumn) {
            $this->tableColumns[$tableColumn->getName()] = $tableColumn->getTypeIdentifier();
        }

        $this->tableName = $tableDefinition->getTableName();
    }

    public function search(array $where = [], int $limit = 1000) : EntityCollection {
        $conn = DatabaseConnection::getConnection();
        $qb = $conn->createQueryBuilder();
        $query = $qb->select('*')->from($this->tableName)->setMaxResults($limit);
        $whereFunction = 'where';
        foreach($where as $column => $value) {
            if(is_string($value)) {
                $query->$whereFunction("$column LIKE ?");
            }
            else {
                $query->$whereFunction("$column = ?");
            }

            $whereFunction = 'andWhere';
        }

        /** @var EntityCollection $entityCollection */
        $entityCollection = new EntityCollection($this->getEntityClass());

        try {
            $stmt = $conn->prepare($query->getSQL());
            $count = 1;
            foreach($where as $column => $value) {
                $stmt->bindValue($count, $value);
                $count++;
            }

            $queryResult = $stmt->executeQuery();

            while($row = $queryResult->fetchAssociative()) {
                if(isset($row['id'])) {
                    $row['id'] = UUID::bytesToHex($row['id']);
                }
                $entityCollection->add($this->arrayToEntity($row));
            }
        } catch (Exception $e) {
            if(MODE == 'dev') { dd($e); }
            return $entityCollection;
        } catch (\Doctrine\DBAL\Driver\Exception $e) {
            if(MODE == 'dev') { dd($e); }
            return $entityCollection;
        }


        return $entityCollection;
    }


    public function arrayToEntity(array $values): Entity {
        /** @var Entity $entityClass */
        $entityClass = $this->getEntityClass();
        return $entityClass::getEntityFromArray($values);
    }


    public function upsert(Entity $entity): bool {
        $this->verifyEntityClass($entity);

        if($entity->getId() === null) {
            return $this->insert($entity);
        }
        else {
            return $this->update($entity);
        }
    }

    protected function insert(Entity $entity): bool {
        $entityArray = $entity->toArray();

        $entityArray = $this->prepareValuesForInsert($entityArray);

        try {
            DatabaseConnection::getConnection()->insert(
                $this->tableName,
                $entityArray,
                $this->getTypeIdentifiersForColumns(array_keys($entityArray))
            );
        }
        catch (\Exception $e) {
            return false;
        }

        $this->adjustEntityAfterSuccessfulInsert($entity, $entityArray);

        return true;
    }

    abstract protected function prepareValuesForInsert(array $values): array;

    abstract protected function adjustEntityAfterSuccessfulInsert(Entity $entity, array $insertedValues): void;

    protected function update(Entity $entity): bool {
        $now = new \DateTime();

        $entityArray = $entity->toArray();

        $filterColumns = $this->getUpdateFilterColumnsFromValues($entityArray);

        $entityArray = $this->prepareValuesForUpdate($entityArray);

        try {
            DatabaseConnection::getConnection()->update(
                $this->tableName,
                $entityArray,
                $filterColumns,
                $this->getTypeIdentifiersForColumns(array_keys($entityArray))
            );
        }
        catch (\Exception $e) {
            return false;
        }

        $this->adjustEntityAfterSuccessfulUpdate($entity, $entityArray);

        return true;
    }

    abstract protected function getUpdateFilterColumnsFromValues(array $updateValues): array;

    abstract protected function prepareValuesForUpdate(array $updateValues): array;

    abstract protected function adjustEntityAfterSuccessfulUpdate(Entity $entity, array $updatedValues): void;


    protected function getTypeIdentifiersForColumns(array $columns): array {
        $identifiers = [];
        foreach($columns as $column) {
            if(isset($this->tableColumns[$column])) {
               $identifiers[] = $this->tableColumns[$column];
            }
            else {
                $identifiers[] = \PDO::PARAM_NULL;
            }
        }
        return $identifiers;
    }

    protected function verifyEntityClass(Entity $entity) {
        $desiredEntityClass = $this->getEntityClass();
        if(!($entity instanceof $desiredEntityClass)) {
            throw new WrongEntityForRepositoryException(get_class($entity), $desiredEntityClass, self::class);
        }
    }


}