<?php declare(strict_types = 1);

namespace Tally\Expression;

enum Operator: string
{

	case Add = '+';
	case Subtract = '-';
	case Multiply = '*';
	case Divide = '/';
	case Power = '^';

}
