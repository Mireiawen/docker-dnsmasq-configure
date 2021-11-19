<?php
declare(strict_types = 1);

namespace Mireiawen\dnsconfig;

use Mireiawen\Configuration\InvalidFile;
use Mireiawen\Configuration\YAML;

/**
 * The main application class
 *
 * @package Mireiawen\dnsconfig
 */
class Application
{
	/**
	 * The configuration instance
	 *
	 * @var YAML
	 */
	protected YAML $Config;
	
	/**
	 * The template engine
	 *
	 * @var \Smarty
	 */
	protected \Smarty $Smarty;
	
	/**
	 * Load a configuration file
	 *
	 * @param string $filename
	 *
	 * @return YAML
	 *
	 * @throws InvalidFile
	 *    In case unable to load the file
	 */
	public static function LoadConfig(string $filename) : YAML
	{
		return new YAML($filename);
	}
	
	/**
	 * Application constructor
	 *
	 * @param YAML $Config
	 *    The configuration to use
	 *
	 * @param \Smarty|null $Smarty
	 *    The Smarty instance to use, use NULL to create one
	 */
	public function __construct(YAML $Config, ?\Smarty $Smarty = NULL)
	{
		$this->Config = $Config;
		
		if ($Smarty === NULL)
		{
			$this->Smarty = new \Smarty();
		}
		else
		{
			$this->Smarty = $Smarty;
		}
		
		$this->Smarty->assign('configuration', $this->Config);
	}
	
	/**
	 * @throws \SmartyException
	 *    In case of Smarty template errors
	 *
	 * @throws \RuntimeException
	 *    In case of invalid configuration file
	 */
	public function Run() : void
	{
		$domains = $this->Config->Get('domains');
		if (!is_array($domains))
		{
			$this->ConfigurationError(\sprintf(\_('The key %s is not of expected format of %s'), 'domains', 'array'));
		}
		
		// The start of the file
		echo $this->TemplatePiece('files/head.tpl');
		
		// The actual records
		foreach ($domains as $domain)
		{
			echo $this->ParseDomain($domain);
		}
		
		// The end of the file
		echo $this->TemplatePiece('files/tail.tpl');
	}
	
	/**
	 * Throw an error message
	 *
	 * @param string $message
	 *    The message to show
	 *
	 * @throws \RuntimeException
	 */
	protected function ConfigurationError(string $message) : void
	{
		throw new \RuntimeException(\sprintf(\_('Unable to parse the configuration file: %s'), '', $message));
	}
	
	/**
	 * @param string $piece
	 *
	 * @return string
	 * @throws \SmartyException
	 */
	protected function TemplatePiece(string $piece) : string
	{
		$template = $this->Smarty->createTemplate($piece);
		return $template->fetch();
	}
	
	/**
	 * Parse a network domain
	 *
	 * @param array $domain
	 *    The network domain
	 *
	 * @return string
	 *    The templated records
	 *
	 * @throws \SmartyException
	 *    In case of Smarty template errors
	 *
	 * @throws \RuntimeException
	 *    In case of invalid configuration file
	 */
	protected function ParseDomain(array $domain) : string
	{
		// Make sure the domain name is set
		if (!isset($domain['name']))
		{
			$this->ConfigurationError(\sprintf(\_('The %s is missing a %s'), \_('domain'), 'name'));
		}
		$name = $domain['name'];
		
		// Check the hosts
		$hosts = $domain['hosts'] ?? [];
		if (!\is_array($hosts))
		{
			$this->ConfigurationError(\sprintf(\_('The key %s is not of expected format of %s'), 'hosts', 'array'));
		}
		
		// Load the template
		$template = $this->Smarty->createTemplate('files/network.tpl');
		$template->assign('network', $domain);
		
		// Go through the hosts
		\ob_start();
		foreach ($hosts as $host)
		{
			echo $this->ParseHost($host, $name);
		}
		
		// Dynamic range
		if (isset($domain['dynamic']))
		{
			echo $this->ParseDynamicRange($domain['dynamic'], $name);
		}
		
		$records = \ob_get_clean();
		$template->assign('records', $records);
		
		// And return the templated output
		return $template->fetch();
	}
	
	/**
	 * Parse the host data array into records
	 *
	 * @param array $host
	 *    The host data array
	 *
	 * @param string $domain
	 *    The domain name
	 *
	 * @return string
	 *    The host record
	 *
	 * @throws \SmartyException
	 *    In case of Smarty template errors
	 *
	 * @throws \RuntimeException
	 *    In case of configuration errors
	 */
	protected function ParseHost(array $host, string $domain) : string
	{
		// Make sure the name for the host is set
		if (!isset($host['name']))
		{
			$this->ConfigurationError(\sprintf(\_('The %s is missing a %s'), \_('host'), 'name'));
		}
		$name = $host['name'];
		
		// Make sure the IP for the host is set
		if (!isset($host['ip']))
		{
			$this->ConfigurationError(\sprintf(\_('The %s is missing a %s'), \_('host'), 'ip'));
		}
		$ip = $host['ip'];
		
		// Check for the aliases
		$aliases = $host['aliases'] ?? [];
		if (!\is_array($aliases))
		{
			$this->ConfigurationError(\sprintf(\_('The key %s is not of expected format of %s'), 'aliases', 'array'));
		}
		
		// Check for the extra addresses
		$addresses = $host['addresses'] ?? [];
		if (!\is_array($addresses))
		{
			$this->ConfigurationError(\sprintf(\_('The key %s is not of expected format of %s'), 'addresses', 'array'));
		}
		
		// Check for the extra FQDNs
		$fqdns = $host['fqdns'] ?? [];
		if (!\is_array($fqdns))
		{
			$this->ConfigurationError(\sprintf(\_('The key %s is not of expected format of %s'), 'fqdns', 'array'));
		}
		
		// Get the reverse IP
		$reverse = $this->IPtoReverse($ip);
		
		// Go through the aliases
		$aliasdomains = [];
		foreach ($aliases as $alias)
		{
			$aliasdomains[] = \sprintf('%s.%s', $alias, $domain);
		}
		
		// Go through the addresses
		$addressdomains = [];
		foreach ($addresses as $address)
		{
			$addressdomains[] = \sprintf('%s.%s', $address, $domain);
		}
		foreach ($fqdns as $address)
		{
			$addressdomains[] = $address;
		}
		
		$template = $this->Smarty->createTemplate('files/host.tpl');
		$template->assign('host', $host);
		$template->assign('address', \sprintf('%s.%s', $name, $domain));
		$template->assign('ip', $ip);
		$template->assign('aliases', $aliasdomains);
		$template->assign('addresses', $addressdomains);
		$template->assign('reverse', $reverse);
		return $template->fetch();
	}
	
	/**
	 * Parse the dynamic range
	 *
	 * @param array $dynamic
	 *    The dynamic range data array
	 *
	 * @param string $domain
	 *    The domain name
	 *
	 * @return string
	 *    The host records
	 *
	 * @throws \SmartyException
	 *    In case of Smarty template errors
	 *
	 * @throws \RuntimeException
	 *    In case of configuration errors
	 *
	 */
	protected function ParseDynamicRange(array $dynamic, string $domain) : string
	{
		if (!is_array($dynamic))
		{
			$this->ConfigurationError(\sprintf(\_('The key %s is not of expected format of %s'), 'dynamic', 'array'));
		}
		
		if (!isset($dynamic['start']))
		{
			$this->ConfigurationError(_('The dynamic configuration is missing start'));
		}
		
		if (!isset($dynamic['end']))
		{
			$this->ConfigurationError(_('The dynamic configuration is missing end'));
		}
		
		if (!isset($dynamic['template']))
		{
			$this->ConfigurationError(_('The dynamic configuration is missing template'));
		}
		
		$ip_start = $dynamic['start'];
		if (\filter_var($ip_start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === FALSE)
		{
			$this->ConfigurationError(\sprintf(\_('Invalid IP address detected: %s'), $ip_start));
		}
		
		$ip_end = $dynamic['end'];
		if (\filter_var($ip_end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === FALSE)
		{
			$this->ConfigurationError(\sprintf(\_('Invalid IP address detected: %s'), $ip_end));
		}
		
		$comment = $dynamic['comment'] ?? '';
		
		$dynamic_template = $dynamic['template'];
		
		[$start_a, $start_b, $start_c, $start_d] = \explode('.', $ip_start);
		[$end_a, $end_b, $end_c, $end_d] = \explode('.', $ip_end);
		
		ob_start();
		for ($a = $start_a; $a <= $end_a; $a++)
		{
			for ($b = $start_b; $b <= $end_b; $b++)
			{
				for ($c = $start_c; $c <= $end_c; $c++)
				{
					for ($d = $start_d; $d < $end_d; $d++)
					{
						$ip = \sprintf('%s.%s.%s.%s', $a, $b, $c, $d);
						$reverse = $this->IPtoReverse($ip);
						
						$template = $this->Smarty->createTemplate('files/host.tpl');
						if ($comment)
						{
							$template->assign('host', ['comment' => $comment]);
							$comment = '';
						}
						$template->assign('a', $a);
						$template->assign('b', $b);
						$template->assign('c', $c);
						$template->assign('d', $d);
						$template->assign('ip', $ip);
						$template->assign('reverse', $reverse);
						$template->assign('aliases', []);
						$template->assign('addresses', []);
						$name = $template->fetch(\sprintf('string:%s', $dynamic_template));
						$template->assign('address', \sprintf('%s.%s', $name, $domain));
						$template->display();
					}
				}
			}
		}
		return ob_get_clean();
	}
	
	/**
	 * Get the reverse DNS notation of the IP address,
	 * in form of d.c.b.a.in-addr.arpa
	 *
	 * @param string $ip
	 *    The IP address to convert
	 *
	 * @return string
	 *    The reverse DNS notation of the IP address
	 */
	protected function IPtoReverse(string $ip) : string
	{
		// Validate the address as IPv4
		if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === FALSE)
		{
			$this->ConfigurationError(\sprintf(\_('Invalid IP address detected: %s'), $ip));
		}
		
		[$a, $b, $c, $d] = \explode('.', $ip);
		return \sprintf('%s.%s.%s.%s.in-addr.arpa', $d, $c, $b, $a);
	}
}
