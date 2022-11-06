<?php

declare(strict_types=1);

namespace margusk\Accessors\Attr;

use Attribute;
use margusk\Accessors\Attr;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Template\Contract as TemplateContract;

use function is_subclass_of;

#[Attribute(Attribute::TARGET_CLASS)]
class Template extends Attr
{
    /** @var mixed[] */
    private array $ctorArgs;

    /** @var TemplateContract|null */
    private ?TemplateContract $instance = null;

    /**
     * @param class-string<TemplateContract>  $template
     * @param mixed[] ...$ctorArgs
     */
    public function __construct(
        private string $template,
        mixed ...$ctorArgs
    ) {
        if (!is_subclass_of($template, TemplateContract::class)) {
            throw InvalidArgumentException::dueTemplateMustImplementValidContract($template);
        }

        parent::__construct();

        $this->ctorArgs = $ctorArgs;
    }

    /**
     * @return TemplateContract
     */
    public function instance(): TemplateContract
    {
        if (null === $this->instance) {
            $this->instance = new $this->template(...$this->ctorArgs);
        }

        return $this->instance;
    }
}
