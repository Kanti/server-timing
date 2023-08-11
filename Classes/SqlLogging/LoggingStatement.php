<?php

// can be used instead after TYPO3 support is set to >=12

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

//
//use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
//use Doctrine\DBAL\Driver\Result as ResultInterface;
//use Doctrine\DBAL\Driver\Statement as StatementInterface;
//
//if (!class_exists(AbstractStatementMiddleware::class)) {
//    return;
//}
//
//final class LoggingStatement extends AbstractStatementMiddleware
//{
//    public function __construct(StatementInterface $statement, private readonly DoctrineSqlLogger $logger, private readonly string $sql)
//    {
//        parent::__construct($statement);
//    }
//
//    public function execute($params = null): ResultInterface
//    {
//        $this->logger->startQuery($this->sql);
//        $result = parent::execute($params);
//        $this->logger->stopQuery();
//
//        return $result;
//    }
//}
