<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: Sep 4, 2005
 * Copyright (C) 2005 Ryan Gilfether
 *
 * This file is part of phpCrypt
 *
 * phpCrypt is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 */

namespace PHP_Crypt;

require_once(dirname(__FILE__)."/Padding.php");
include_once(dirname(__FILE__)."/phpCrypt.php");

/**
 * A base class that should not be used directly. Instead, all mode classes should
 * extend this one. It provides tools that may be used for any type of Mode.
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2005 Ryan Gilfether
 */
abstract class Mode
{
	/**
	 * @type object $cipher The cipher object used within the mode
	 */
	protected $cipher = null;

	/**
	 * @type string $iv The IV used for the mode, not all Modes
	 * use an IV so this may be empty
	 */
	protected $iv = "";

	/**
	 * @type string $register For modes that use a register to do
	 * encryption/decryption. This stores the unencrypted register.
	 */
	protected $register = "";

	/**
	 * @type string $enc_register For modes that use a register to do
	 * encryption/decryption. This stores the encrypted register
	 */
	protected $enc_register = "";

	/**
	 * @type integer $block_size The byte size of the block to
	 * encrypt/decrypt for the Mode
	 */
	private $block_size = 0;

	/** @type string $mode_name The name of mode currently used */
	private $mode_name = "";

	/**
	 * @type string $padding The type of padding to use when required.
	 * Padding types are defined in phpCrypt class. Defaults to
	 * PHP_Crypt::PAD_ZERO
	 */
	private $padding = PHP_Crypt::PAD_ZERO;


	/**
	 * Constructor
	 * Sets the cipher object that will be used for encryption
	 *
	 * @param object $cipher One of the phpCrypt encryption cipher objects
	 * @param string $mode_name The name of phpCrypt's modes, as defined in the mode
	 * @return void
	 */
	protected function __construct($mode_name, $cipher)
	{
		$this->name($mode_name);
		$this->cipher($cipher);
	}


	/**
	 * Destructor
	 *
	 * @return void
	 */
	protected function __destruct()
	{

	}



	/**********************************************************************
	 * ABSTRACT METHODS
	 *
	 * The abstract methods required by inheriting classes to implement
	 **********************************************************************/

	/**
	 * All modes must have encrypt(), which implements
	 * the mode using the cipher's encrypiton algorithm
	 *
	 * @param string $text A String to encrypt
	 * @return boolean Always returns true
	 */
	abstract public function encrypt(&$text);


	/**
	 * All modes must have decrypt(), which implements
	 * the mode using the cipher's encryption algorithm
	 *
	 * @param string $text A String to decrypt
	 * @return boolean Always returns true
	 */
	abstract public function decrypt(&$text);


	/**
	 * Indicates whether or not a mode requires an IV
	 *
	 * @return boolean Always returns true or false
	 */
	abstract public function requiresIV();




	/**********************************************************************
	 * PUBLIC METHODS
	 *
	 **********************************************************************/

	/**
	 * Create an IV if the Mode used requires an IV.
	 * The IV should be saved and used for Encryption/Decryption
	 * of the same blocks of data.
	 * There are 3 ways to auto generate an IV by setting $src parameter
	 * PHP_Crypt::IV_RAND - Default, uses mt_rand()
	 * PHP_Crypt::IV_DEV_RAND - Unix only, uses /dev/random
	 * PHP_Crypt::IV_DEV_URAND - Unix only, uses /dev/urandom
	 * PHP_Crypt::IV_WIN_COM - Windows only, uses Microsoft's CAPICOM SDK
	 *
	 * @param string $src Optional, Sets how the IV is generated, must be
	 *	one of the predefined PHP_Crypt IV constants. Defaults to
	 *	PHP_Crypt::IV_RAND if none is given.
	 * @return string The IV that is being used by the mode
	 */
	public function createIV($src = null)
	{
		// if the mode does not use an IV, lets not waste time
		if(!$this->requiresIV())
			return false;

		$iv = "";
		$err_msg = "";

		if($src == PHP_Crypt::IV_DEV_RAND)
		{
			if(file_exists(PHP_Crypt::IV_DEV_RAND))
				$iv = file_get_contents(PHP_CRYPT::IV_DEV_RAND, false, null, 0, $this->block_size);
			else
				$err_msg = PHP_Crypt::IV_DEV_RAND." not found";
		}
		else if($src == PHP_Crypt::IV_DEV_URAND)
		{
			if(file_exists(PHP_Crypt::IV_DEV_URAND))
				$iv = file_get_contents(PHP_CRYPT::IV_DEV_URAND, false, null, 0, $this->block_size);
			else
				$err_msg = PHP_Crypt::IV_DEV_URAND." not found";
		}
		else if($src == PHP_Crypt::IV_WIN_COM)
		{
			if(extension_loaded('com_dotnet'))
			{
				// http://msdn.microsoft.com/en-us/library/aa388176(VS.85).aspx
				try
				{
					$com = @new \COM("CAPICOM.Utilities.1");
					$iv = $com->GetRandom($this->block_size, 0);

					// if we ask for binary data PHP munges it, so we
					// request base64 return value.  We squeeze out the
					// redundancy and useless ==CRLF by hashing...
					if($iv)
						$iv = md5($iv, true);
					else
						$err_msg  = "Windows COM failed to create an IV";
				}
				catch(Exception $ex)
				{
					$err_msg  = "Windows COM exception: ".$ex->getMessage();
				}
			}
			else
				$err_msg = "The COM_DOTNET extension is not loaded";
		}

		// trigger a warning if something went wrong
		if($err_msg != "")
			trigger_error("$err_msg. Defaulting to PHP_CRYPT::IV_RAND", E_USER_WARNING);

		// if for some reason the iv was not created properly, or PHP_Crypt::IV_RAND
		// was given for the $src param, create the IV using mt_rand(). It's not secure
		// but we have no other choice
		if(strlen($iv) < $this->block_size)
		{
			$iv = "";
			for($i = 0; $i < $this->block_size; ++$i)
				$iv .= chr(mt_rand(0, 255));
		}

		// now store the IV we created
		return $this->IV($iv);
	}


	/**
	 * Sets or Returns an IV for the mode to use
	 *
	 * @param string $iv Optional, An IV to use for the mode and cipher selected
	 * @return void
	 */
	public function IV($iv = null)
	{
		if($iv != null)
		{
			// check that the iv is the correct length,
			$len = strlen($iv);
			if($len != $this->block_size)
			{
				$msg = "Incorrect IV size. Supplied length: $len bytes, Required: {$this->block_size} bytes";
				trigger_error($msg, E_USER_WARNING);
			}

			$this->clearRegisters();
			$this->register = $iv;
			$this->iv = $iv;
		}

		return $this->iv;
	}


	/**
	 * Checks to see if the current mode requires an IV and that it is set
	 * if it is required. Triggers E_USER_WARNING an IV is required and not set
	 *
	 * @return void
	 */
	public function checkIV()
	{
		if($this->requiresIV() && strlen($this->register) == 0)
		{
			$msg = strtoupper($this->mode_name)." mode requires an IV or the IV is empty";
			trigger_error($msg, E_USER_WARNING);
		}
	}


	/**
	 * Sets and returns the name of the mode being used
	 * If $name parameter is set, sets the mode. If
	 * $name is not set, returns the current mode in use
	 *
	 * @param string $name Optional, One of the predefined
	 * 	phpCrypt mode constant names
	 * @return string One of the predefined phpCrypt mode
	 * 	constant mode names
	 */
	public function name($name = "")
	{
		if($name != "")
			$this->mode_name = $name;

		return $this->mode_name;
	}


	/**
	 * Sets or Returns the padding type used with the mode
	 * If the $type parameter is not given, this function
	 * returns the the padding type only.
	 *
	 * @param string $type One of the predefined padding types
	 * @return void
	 */
	public function padding($type = "")
	{
		if($type != "")
			$this->padding = $type;

		return $this->padding;
	}


	/**
	 * Returns or Sets the cipher object being used
	 * If the $cipher parameter is set with a cipher object,
	 * the cipher current cipher will be set to this cipher.
	 * If the $cipher parameter is not set, returns the
	 * current cipher object being used.
	 *
	 * @param object $cipher Optional, An object of type Cipher
	 * @return object An object of type Cipher
	 */
	public function cipher($cipher = null)
	{
		if(is_object($cipher))
			$this->cipher = $cipher;

		return $this->cipher;
	}




	/**********************************************************************
	 * PROTECTED METHODS
	 *
	 **********************************************************************/

	/**
	 * Pads str so that final block is $block_bits in size, if the final block
	 * is $block_bits, then an additional block is added that is $block_bits in size
	 * The padding should be set by phpCrypt::setPadding()
	 *
	 * @param string $str the string to be padded
	 * @return boolean Returns true
	 */
	protected function pad(&$str)
	{
		$len = strlen($str);
		$bytes = $this->blockSize(); // returns bytes

		// now determine the next multiple of blockSize(), then find
		// the difference between that and the length of $str,
		// this is how many padding bytes we will need
		$num = ceil($len / $bytes) * $bytes;
		$num = $num - $len;

		Padding::pad($str, $num, $this->padding);
		return true;
	}


	/**
	 * Strip out the padded blocks created from Pad().
	 * Padding type should be set by phpCrypt::setPadding()
	 *
	 * @param string $str the string to strip padding from
	 * @return boolean Returns True
	 */
	protected function strip(&$str)
	{
		Padding::strip($str, $this->padding);
		return true;
	}



	/**
	 * Sets or Returns the size of block (in bytes) the Mode should use
	 * for the Cipher's Encryption, for example DES uses 64bit data, which
	 * means our BlockSize will be 8 bytes
	 *
	 * @param int $bytes Options, The size of the block in bytes
	 * @return int The currently set block size, in bytes
	 */
	protected function blockSize($bytes = 0)
	{
		if($bytes > 0)
			$this->block_size = $bytes;

		return $this->block_size;
	}




	/**********************************************************************
	 * PRIVATE METHODS
	 *
	 **********************************************************************/

	/**
	 * Clears the registers used for some modes
	 *
	 * @return void
	 */
	private function clearRegisters()
	{
		$this->register = "";
		$this->enc_register = "";
	}
}
?>