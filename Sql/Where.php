<?php

declare(strict_types=1);

namespace Qant\ORM\Sql;

use Qant\ORM\Gateway;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Where as ZendWhere;

class Where extends ZendWhere
{
    /**
     * gateway
     *
     * @var mixed
     */
    protected $gateway = null;

    /**
     * setGateway
     *
     * @param Gateway\Gateway $_gw
     */
    public function setGateway(Gateway\Gateway $_gw)
    {
        $this->gateway = $_gw;
    }

    /**
     * __invoke
     *
     * @param mixed $_predicates
     * @param mixed $_combination
     */
    public function __invoke($_predicates, $_combination = self::OP_AND) : Where
    {
        $predicateSet = new static();
        $predicateSet->addPredicates($_predicates, $_combination);
        $this->addPredicate($predicateSet, ($this->nextPredicateCombineOperator) ?: $this->defaultCombination);
        return $this;
    }

    /**
     * AND
     *
     * @param mixed $_predicates
     */
    public function AND($_predicates, $_combination = self::OP_AND) : Where
    {
        $predicateSet = new static();

        if (is_callable($_predicates)) {
            $_predicates($predicateSet, $_combination);
        } else {
            $predicateSet->addPredicates($_predicates, $_combination);
        }

        $this->addPredicate($predicateSet, self::OP_AND);
        return $this;
    }

    /**
     * OR
     *
     * @param mixed $_predicates
     */
    public function OR($_predicates, $_combination = self::OP_AND) : Where
    {
        $predicateSet = new static();

        if (is_callable($_predicates)) {
            $_predicates($predicateSet, $_combination);
        } else {
            $predicateSet->addPredicates($_predicates, $_combination);
        }

        $this->addPredicate($predicateSet, self::OP_OR);
        return $this;
    }

    /**
     * operator
     *
     */
    public function operator($_left, $_operator, $_right) : Predicate\Operator
    {
        return new Predicate\Operator($_left, $_operator, $_right);
    }

    /**
     * prepareCurrentTablePredicates
     *
     * @param array $_predicates
     */
    public function prepareCurrentTablePredicates(array $_predicates = null)
    {
        if (! $_predicates) {
            $_predicates = $this->getPredicates();
        }

        foreach ($_predicates as $predicate) {
            # - Recursive prepare
            if ($predicate[1] instanceof Predicate\PredicateSet) {
                $this->prepareCurrentTablePredicates($predicate[1]->getPredicates());
            }

            # - Prepare Expression
            if ($predicate[1] instanceof Predicate\Operator) {
                $predicate[1]->setLeft(
                    str_replace(
                        '@this',
                        $this->gateway->getProcessorPathPreffix(),
                        $predicate[1]->getLeft()
                    )
                );
            } elseif(method_exists($predicate[1], 'setIdentifier')) {
                $predicate[1]->setIdentifier(
                    str_replace(
                        '@this',
                        $this->gateway->getProcessorPathPreffix(),
                        $predicate[1]->getIdentifier()
                    )
                );
            }
        }
    }

    /**
     * replaceReferencePathsToAliases
     *
     * @param array $_referencePaths
     * @param array $_predicates
     */
    public function replaceReferencePathsToAliases(array $_referencePaths, array $_predicates = null)
    {
        if (! $_predicates) {
            $_predicates = $this->getPredicates();
        }

        foreach ($_predicates as $predicate) {

            # - Recursive prepare
            if ($predicate[1] instanceof Predicate\PredicateSet) {
                $this->replaceReferencePathsToAliases($_referencePaths, $predicate[1]->getPredicates());
            }

            # - Prepare Operator
            if (method_exists($predicate[1], 'setLeft')) {
                $predicate[1]->setLeft(
                    str_replace(
                        array_keys($_referencePaths),
                        array_values($_referencePaths),
                        $predicate[1]->getLeft()
                    )
                );
            # - Prepare other predicates
            } elseif(method_exists($predicate[1], 'setIdentifier')) {
                $predicate[1]->setIdentifier(
                    str_replace(
                        array_keys($_referencePaths),
                        array_values($_referencePaths),
                        $predicate[1]->getIdentifier()
                    )
                );
            }
        }
    }

    /**
     * setPredicates
     *
     * @param array $_predicates
     */
    public function setPredicates(array $_predicates)
    {
        $this->predicates = $_predicates;
        return $this;
    }
}
