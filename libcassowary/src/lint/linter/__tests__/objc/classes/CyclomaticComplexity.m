#import <Foundation/Foundation.h>

@interface CyclomaticComplexity : NSObject

- (void)complexMethodWithIfStatements;
- (void)complexMethodWithCaseStatements;
- (void)simpleMethodWithIfStatements;
- (void)simpleMethodWithCaseStatements;
- (BOOL)randomBool;
- (int)randomInt;

@end

@implementation CyclomaticComplexity

- (void)complexMethodWithIfStatements // 1
{
	if ([self randomBool]) // 2
	{
		if ([self randomBool]) // 3
		{
		}
		else
		{
		}
	}
	else if ([self randomBool]) // 4
	{
		if ([self randomBool]) // 5
		{
		}
		
		if ([self randomBool]) // 6
		{
		}
		else if ([self randomBool]) // 7
		{
		}
	}
	else if ([self randomBool]) // 8
	{
		if ([self randomBool]) // 9
		{
		}
		else 
		{
			if ([self randomBool]) // 10
			{
			}
		}
	}
	else 
	{
		if ([self randomBool]) // 11
		{
		}
		else 
		{
		}
	}
}

- (void)complexMethodWithCaseStatements // 1
{
	switch ([self randomInt])
	{
		case 0: // 2
		{
		} break;
		case 1: // 3
		{
		} break;
		case 2: // 4
		{
		} break;
		case 3: // 5
		{
		} break;
		case 4: // 6
		{
		} break;
		case 5: // 8
		{
		} break;
		case 6: // 9
		{
		} break;
		case 7: // 10
		{
		} break;
		case 8: // 11
		{
		} break;
		case 9: // 12
		{
		} break;
		default: // 13
		{
		} break;
	}
}

- (void)simpleMethodWithIfStatements
{
	if ([self randomBool]) // 2
	{
		if ([self randomBool]) // 3
		{
		}
		else
		{
		}
	}
	else if ([self randomBool]) // 4
	{
		if ([self randomBool]) // 5
		{
		}
		
		if ([self randomBool]) // 6
		{
		}
		else if ([self randomBool]) // 7
		{
		}
	}
	else if ([self randomBool]) // 8
	{
		if ([self randomBool]) // 9
		{
		}
		else 
		{
			if ([self randomBool]) // 10
			{
			}
		}
	}
	else 
	{
	}
}

- (void)simpleMethodWithCaseStatements
{
	switch ([self randomInt])
	{
		case 0: // 2
		{
		} break;
		case 1: // 3
		{
		} break;
		case 2: // 4
		{
		} break;
		case 3: // 5
		{
		} break;
		case 4: // 6
		{
		} break;
		case 5: // 8
		{
		} break;
		case 6: // 9
		{
		} break;
		default: // 11
		{
		} break;
	}
}

- (BOOL)randomBool
{
	return arc4random()%2 == 0;
}

- (int)randomInt
{
	return arc4random()%100;
}

@end