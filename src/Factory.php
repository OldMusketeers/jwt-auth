<?php

/*
 * This file is part of jwt-auth
 *
 * (c) Sean Tymon <tymon148@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tymon\JWTAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Tymon\JWTAuth\Support\Utils;
use Tymon\JWTAuth\Claims\Factory as ClaimFactory;
use Illuminate\Support\Collection;
use Tymon\JWTAuth\Support\RefreshFlow;
use Tymon\JWTAuth\Support\CustomClaims;
use Tymon\JWTAuth\Validators\PayloadValidator;

class Factory
{
    use RefreshFlow, CustomClaims;

    /**
     * @var \Tymon\JWTAuth\Claims\Factory
     */
    protected $claimFactory;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * @var \Tymon\JWTAuth\Validators\PayloadValidator
     */
    protected $validator;

    /**
     * @var int
     */
    protected $ttl = 60;

    /**
     * @var array
     */
    protected $defaultClaims = ['iss', 'iat', 'exp', 'nbf', 'jti'];

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $claims;

    /**
     * @param \Tymon\JWTAuth\Claims\Factory               $claimFactory
     * @param \Illuminate\Http\Request                    $request
     * @param \Tymon\JWTAuth\Validators\PayloadValidator  $validator
     */
    public function __construct(ClaimFactory $claimFactory, Request $request, PayloadValidator $validator)
    {
        $this->claimFactory = $claimFactory;
        $this->request = $request;
        $this->validator = $validator;

        $this->claims = new Collection;
    }

    /**
     * Create the Payload instance
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function make()
    {
        $claims = $this->buildClaims()->resolveClaims();

        return new Payload($claims, $this->validator, $this->refreshFlow);
    }

    /**
     * Add an array of claims to the Payload
     *
     * @param  array  $claims
     *
     * @return Factory
     */
    public function addClaims(array $claims)
    {
        foreach ($claims as $name => $value) {
            $this->addClaim($name, $value);
        }

        return $this;
    }

    /**
     * Add a claim to the Payload
     *
     * @param  string  $name
     * @param  mixed   $value
     *
     * @return Factory
     */
    public function addClaim($name, $value)
    {
        $this->claims->put($name, $value);

        return $this;
    }

    /**
     * Build the default claims
     *
     * @return Factory
     */
    protected function buildClaims()
    {
        // remove the exp claim if it exists and the ttl is null
        if (is_null($this->ttl) && $key = array_search('exp', $this->defaultClaims)) {
            unset($this->defaultClaims[$key]);
        }

        // add the default claims
        foreach ($this->defaultClaims as $claim) {
            $this->addClaim($claim, call_user_func([$this, $claim]));
        }

        // add custom claims on top, allowing them to overwrite defaults
        $this->addClaims($this->getCustomClaims());

        return $this;
    }

    /**
     * Build out the Claim DTO's
     *
     * @return \Illuminate\Support\Collection
     */
    protected function resolveClaims()
    {
        return $this->claims->map(function ($value, $name) {
            return $this->claimFactory->get($name, $value);
        });
    }

    /**
     * Get the Issuer (iss) claim
     *
     * @return string
     */
    public function iss()
    {
        return $this->request->url();
    }

    /**
     * Get the Issued At (iat) claim
     *
     * @return int
     */
    public function iat()
    {
        return (int) Utils::now()->format('U');
    }

    /**
     * Get the Expiration (exp) claim
     *
     * @return int
     */
    public function exp()
    {
        return (int) Utils::now()->addMinutes($this->ttl)->format('U');
    }

    /**
     * Get the Not Before (nbf) claim
     *
     * @return int
     */
    public function nbf()
    {
        return (int) Utils::now()->format('U');
    }

    /**
     * Get a unique id (jti) for the token
     *
     * @return string
     */
    protected function jti()
    {
        return md5(sprintf('%s.%s', $this->claims->toJson(), Str::quickRandom()));
    }

    /**
     * Set the token ttl (in minutes)
     *
     * @param  int  $ttl
     *
     * @return Factory
     */
    public function setTTL($ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Get the token ttl
     *
     * @return int
     */
    public function getTTL()
    {
        return $this->ttl;
    }

    /**
     * Set the default claims to be added to the Payload
     *
     * @param array $claims
     *
     * @return Factory
     */
    public function setDefaultClaims(array $claims)
    {
        $this->defaultClaims = $claims;

        return $this;
    }

    /**
     * Get the default claims
     *
     * @return array
     */
    public function getDefaultClaims()
    {
        return $this->defaultClaims;
    }

    /**
     * Get the PayloadValidator instance
     *
     * @return \Tymon\JWTAuth\Validators\PayloadValidator
     */
    public function validator()
    {
        return $this->validator;
    }

    /**
     * Magically add a claim
     *
     * @param  string  $method
     * @param  array   $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return Factory
     */
    public function __call($method, $parameters)
    {
        $this->addClaim($method, $parameters[0]);

        return $this;
    }
}
