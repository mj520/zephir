<?php

/*
 * This file is part of the Zephir.
 *
 * (c) Zephir Team <team@zephir-lang.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zephir\Statements;

use Zephir\CompilationContext;
use Zephir\Exception\CompilerException;
use Zephir\Expression\Builder\BuilderFactory;

/**
 * DeclareStatement
 *
 * This creates variables in the current symbol table
 */
class DeclareStatement extends StatementAbstract
{
    /**
     * @param CompilationContext $compilationContext
     * @throws CompilerException
     */
    public function compile(CompilationContext $compilationContext)
    {
        $statement = $this->statement;

        if (!isset($statement['data-type'])) {
            throw new CompilerException("Data type is required", $this->statement);
        }

        $typeInference = $compilationContext->typeInference;
        $symbolTable = $compilationContext->symbolTable;

        foreach ($statement['variables'] as $variable) {
            $varName = $variable['variable'];
            if ($symbolTable->hasVariableInBranch($varName, $compilationContext->branchManager->getCurrentBranch())) {
                throw new CompilerException("Variable '" . $varName . "' is already defined", $variable);
            }

            $currentType = $statement['data-type'];

            /**
             * Replace original data type by the pre-processed infered type
             */
            if ($typeInference) {
                if ($currentType == 'variable') {
                    $type = $typeInference->getInferedType($varName);
                    if (is_string($type)) {
                        $currentType = $type;
                    }
                }
            }

            switch ($currentType) {
                case 'variable':
                case 'array-access':
                case 'property-access':
                case 'static-property-access':
                case 'fcall':
                case 'mcall':
                case 'scall':
                    $currentType = 'variable';
                    break;
            }

            /**
             * Variables are added to the symbol table
             */
            $symbolVariable = $symbolTable->addVariable($currentType, $varName, $compilationContext);
            $varName = $symbolVariable->getName();

            /**
             * Set the node where the variable is declared
             */
            $symbolVariable->setOriginal($variable);

            $symbolVariable->setIsInitialized(true, $compilationContext);
            //$symbolVariable->increaseMutates();

            if ($currentType == 'variable') {
                $symbolVariable->setMustInitNull(true);
                $symbolVariable->setLocalOnly(false);
            }
            //$symbolVariable->increaseVariantIfNull();

            if (isset($variable['expr'])) {
                $builder = BuilderFactory::getInstance();
                $letBuilder = $builder->statements()->let([
                    $builder->operators()
                        ->assignVariable($varName, $builder->raw($variable['expr']))
                ]);

                $letStatement = new LetStatement($letBuilder->build());
                $letStatement->compile($compilationContext);
            } else {
                $symbolVariable->enableDefaultAutoInitValue();
            }
        }
    }

    /**
     * throw exception for invalid default type
     * @param $defaultType
     * @param $dateType
     * @param $variable
     * @throws CompilerException
     */
    public static function invalidDefaultTypeException($defaultType, $dateType, $variable)
    {
        throw new CompilerException('Invalid default type: ' . $defaultType . ' for data type: ' . $dateType, $variable);
    }
}
