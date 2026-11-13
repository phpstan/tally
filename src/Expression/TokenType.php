<?php declare(strict_types = 1);

namespace Tally\Expression;

enum TokenType
{

	case Number;
	case Name;
	case String;
	case Plus;
	case Minus;
	case Star;
	case Slash;
	case Caret;
	case LeftParen;
	case RightParen;
	case LeftBracket;
	case RightBracket;
	case Dot;
	case Comma;
	case End;

}
