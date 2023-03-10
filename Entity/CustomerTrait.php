<?php

namespace Plugin\TwoFactorAuthCustomerSms42\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="two_factor_authed_phone_number", type="string", length=14, nullable=true)
     */
    private $two_factor_authed_phone_number;

    /**
     * @var ?string
     *
     * @ORM\Column(name="two_factor_auth_one_time_token", type="string", length=10, nullable=true)
     */
    private ?string $two_factor_auth_one_time_token;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="two_factor_auth_one_time_token_expire", type="datetimetz", nullable=true)
     */
    private $two_factor_auth_one_time_token_expire;


    /**
     * @return string
     */
    public function getTwoFactorAuthedPhoneNumber(): ?string
    {
        return $this->two_factor_authed_phone_number;
    }

    /**
     * @param string $two_factor_authed_phone_number
     */
    public function setTwoFactorAuthedPhoneNumber(string $two_factor_authed_phone_number): void
    {
        $this->two_factor_authed_phone_number = $two_factor_authed_phone_number;
    }

    /**
     * @return string
     */
    public function createTwoFactorAuthOneTimeToken(): ?string
    {
        $now = new \DateTime();

        // TODO: なんちゃって
        $token = '';
        for ($i = 0; $i < 6; $i++) {
            $token .= (string)rand(0, 9);
        }

        $this->setTwoFactorAuthOneTimeToken($token);
        $this->setTwoFactorAuthOneTimeTokenExpire($now->modify('+5 mins'));
        return $token;
    }

    /**
     * @return string
     */
    public function getTwoFactorAuthOneTimeToken(): ?string
    {
        return $this->two_factor_auth_one_time_token;
    }

    /**
     * @param string $two_factor_auth_one_time_token
     */
    public function setTwoFactorAuthOneTimeToken(?string $two_factor_auth_one_time_token): void
    {
        $this->two_factor_auth_one_time_token = $two_factor_auth_one_time_token;
    }

    /**
     * Set oneTimeTokenExpire.
     *
     * @param \DateTime|null $resetExpire
     *
     * @return Customer
     */
    public function setTwoFactorAuthOneTimeTokenExpire($deviceAuthOneTimeTokenExpire = null)
    {
        $this->two_factor_auth_one_time_token_expire = $deviceAuthOneTimeTokenExpire;

        return $this;
    }

    /**
     * Get resetExpire.
     *
     * @return \DateTime|null
     */
    public function getTwoFactorAuthOneTimeTokenExpire()
    {
        return $this->two_factor_auth_one_time_token_expire;
    }

}
