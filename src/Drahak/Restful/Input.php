<?php
namespace Drahak\Restful;

use Drahak\Restful\Mapping\MapperContext;
use IteratorAggregate;
use Drahak\Restful\IInput;
use Drahak\Restful\Mapping\IMapper;
use Nette\MemberAccessException;
use Nette\Object;
use Nette\Http;
use Nette\Utils\Json;
use Nette\Utils\Strings;

/**
 * Request Input parser
 * @package Drahak\Restful\Input
 * @author Drahomír Hanák
 *
 * @property-read array $data
 */
class Input extends Object implements IteratorAggregate, IInput
{

	/** @var \Nette\Http\IRequest */
	private $httpRequest;

	/** @var array */
	private $data;

	/** @var IMapper */
	protected $mapper;

	public function __construct(Http\IRequest $httpRequest, MapperContext $mapperContext)
	{
		$this->httpRequest = $httpRequest;
		try {
			$this->mapper = $mapperContext->getMapper($httpRequest->getHeader('Content-Type'));
		} catch (InvalidStateException $e) {
			// No mapper for this content type - ignore in this step
		}
	}

	/**
	 * Set input mapper
	 * @param IMapper $mapper
	 * @return IInput
	 */
	public function setMapper(IMapper $mapper)
	{
		$this->mapper = $mapper;
		return $this;
	}

	/**
	 * Get parsed input data
	 * @return array
	 */
	public function getData()
	{
		if (!$this->data) {
			$this->data = $this->parseData();
		}
		return $this->data;
	}

	/**
	 * @return array|mixed|\Traversable
	 */
	private function parseData()
	{
		if ($input = file_get_contents('php://input')) {
			if (!$this->mapper) {
				throw new InvalidStateException('No mapper defined');
			}
			return $this->mapper->parseRequest($input);
		} else if ($this->httpRequest->getPost()) {
			return $this->httpRequest->getPost();
		}
		return $this->httpRequest->getQuery();
	}

	/******************** Magic methods ********************/

	/**
	 * @param string $name
	 * @return mixed
	 *
	 * @throws \Exception|\Nette\MemberAccessException
	 */
	public function &__get($name)
	{
		try {
			return parent::__get($name);
		} catch(MemberAccessException $e) {
			$data = $this->getData();
			if (isset($data[$name])) {
				return $data[$name];
			}
			throw $e;
		}
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		$isset = parent::__isset($name);
		if ($isset) {
			return TRUE;
		}
		$data = $this->getData();
		return isset($data[$name]);
	}

	/******************** Iterator aggregate interface ********************/

	/**
	 * Get input data iterator
	 * @return InputIterator
	 */
	public function getIterator()
	{
		return new InputIterator($this);
	}


}