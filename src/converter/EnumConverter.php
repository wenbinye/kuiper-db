<?php

declare(strict_types=1);

namespace kuiper\db\converter;

use kuiper\db\metadata\Column;
use kuiper\helper\Enum;

class EnumConverter implements AttributeConverterInterface
{
    /**
     * @var bool
     */
    private $ordinal;

    public function __construct(bool $ordinal)
    {
        $this->ordinal = $ordinal;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseColumn($attribute, Column $column)
    {
        if ($attribute instanceof Enum) {
            return $this->ordinal ? $attribute->ordinal() : $attribute->name();
        }
        throw new \InvalidArgumentException('attribute is not enum type');
    }

    /**
     * {@inheritdoc}
     */
    public function convertToEntityAttribute($dbData, Column $column)
    {
        $enumType = $column->getType()->getName();

        return call_user_func([$enumType, $this->ordinal ? 'fromOrdinal' : 'fromName'], $dbData);
    }
}
