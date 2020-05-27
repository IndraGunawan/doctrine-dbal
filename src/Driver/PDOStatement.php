<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ForwardCompatibility\Driver\ResultStatement as ForwardCompatibleResultStatement;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use IteratorAggregate;
use PDO;
use function array_slice;
use function assert;
use function func_get_args;
use function is_array;

/**
 * The PDO implementation of the Statement interface.
 * Used by all PDO-based drivers.
 */
class PDOStatement implements IteratorAggregate, Statement, ForwardCompatibleResultStatement
{
    private const PARAM_TYPE_MAP = [
        ParameterType::NULL         => PDO::PARAM_NULL,
        ParameterType::INTEGER      => PDO::PARAM_INT,
        ParameterType::STRING       => PDO::PARAM_STR,
        ParameterType::BINARY       => PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
        ParameterType::BOOLEAN      => PDO::PARAM_BOOL,
    ];

    private const FETCH_MODE_MAP = [
        FetchMode::ASSOCIATIVE => PDO::FETCH_ASSOC,
        FetchMode::NUMERIC     => PDO::FETCH_NUM,
        FetchMode::MIXED       => PDO::FETCH_BOTH,
        FetchMode::COLUMN      => PDO::FETCH_COLUMN,
    ];

    /** @var \PDOStatement */
    private $stmt;

    public function __construct(\PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use one of the fetch- or iterate-related methods.
     */
    public function setFetchMode($fetchMode)
    {
        $fetchMode = $this->convertFetchMode($fetchMode);

        try {
            return $this->stmt->setFetchMode($fetchMode);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $type = $this->convertParamType($type);

        try {
            return $this->stmt->bindValue($param, $value, $type);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @param mixed    $column
     * @param mixed    $variable
     * @param int      $type
     * @param int|null $length
     * @param mixed    $driverOptions
     *
     * @return bool
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        $type = $this->convertParamType($type);

        try {
            return $this->stmt->bindParam($column, $variable, $type, ...array_slice(func_get_args(), 3));
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        try {
            return $this->stmt->closeCursor();
        } catch (\PDOException $exception) {
            // Exceptions not allowed by the interface.
            // In case driver implementations do not adhere to the interface, silence exceptions here.
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        try {
            return $this->stmt->execute($params);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function rowCount() : int
    {
        return $this->stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchNumeric(), fetchAssociative() or fetchOne() instead.
     */
    public function fetch($fetchMode = null)
    {
        try {
            if ($fetchMode === null) {
                return $this->stmt->fetch();
            }

            return $this->stmt->fetch(
                $this->convertFetchMode($fetchMode)
            );
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchAllNumeric(), fetchAllAssociative() or fetchColumn() instead.
     */
    public function fetchAll($fetchMode = null)
    {
        try {
            if ($fetchMode === null) {
                $data = $this->stmt->fetchAll();
            } else {
                $data = $this->stmt->fetchAll(
                    $this->convertFetchMode($fetchMode)
                );
            }
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        assert(is_array($data));

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchOne() instead.
     */
    public function fetchColumn()
    {
        try {
            return $this->stmt->fetchColumn();
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric()
    {
        return $this->fetch(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric() : array
    {
        return $this->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @param int $type Parameter type
     */
    private function convertParamType(int $type) : int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw new InvalidArgumentException('Invalid parameter type: ' . $type);
        }

        return self::PARAM_TYPE_MAP[$type];
    }

    /**
     * Converts DBAL fetch mode to PDO fetch mode
     *
     * @param int $fetchMode Fetch mode
     */
    private function convertFetchMode(int $fetchMode) : int
    {
        if (! isset(self::FETCH_MODE_MAP[$fetchMode])) {
            throw new InvalidArgumentException('Invalid fetch mode: ' . $fetchMode);
        }

        return self::FETCH_MODE_MAP[$fetchMode];
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        yield from $this->stmt;
    }
}
