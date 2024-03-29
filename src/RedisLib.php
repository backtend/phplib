<?php
declare (strict_types=1);

namespace backtend\phplib;


/**
 * Class RedisLib
 * @package backtend\phplib
 *
 * RedisLib::instance()->incr('key');
 * RedisLib::instance(1, 'default')->incr('key');
 * RedisLib::factory(1, 'default', ['timeout'=>30])->incr('key');
 *
 * RedisLib::factory()->incr('key');
 * RedisLib::factory(1, 'default')->incr('key');
 * RedisLib::factory(1, 'default', ['persistent'=>true])->incr('key');
 */
class RedisLib
{
    /**
     * @var array
     */
    private static $_instances = []; //不同主机不同库的全部静态实例

    /**
     * RedisLib constructor.
     */
    public function __construct()
    {
    }

    /**
     * 禁止clone
     */
    private function __clone()
    {
    }

    /**
     * 获取静态存在链接的实例
     * @param int $select 多少号库
     * @param string $connection 链接配置default|queue|etc..
     * @param array $options 选项配置
     * @return \Redis
     * @throws \Exception
     */
    public static function instance(int $select = null, string $connection = null, array $options = []): \Redis
    {
        $index = strval($connection) . '_' . intval($select);
        if (!isset(self::$_instances[$index])) {
            //工厂模式产生一个实例
            self::$_instances[$index] = self::factory($select, $connection, $options);
        }

        return self::$_instances[$index];//返回redis对象
    }


    /**
     * 获取一个发起新链接的实例
     * @param int|null $select 多少号库
     * @param string $connection 链接配置default|queue|etc..
     * @param array $options 选项配置
     * @return \Redis
     * @throws \Exception
     */
    public static function factory(int $select = null, string $connection = null, array $options = []): \Redis
    {
        $instance = new \Redis();
        $connection = $connection === null ? 'default' : strval($connection);
        $host = $options['host'] ?? strval(environ(sprintf('redis.%s.host', $connection), '127.0.0.1'));
        $port = $options['port'] ?? intval(environ(sprintf('redis.%s.port', $connection), '6379'));
        $password = $options['password'] ?? strval(environ(sprintf('redis.%s.password', $connection), ''));
        $timeout = $options['timeout'] ?? floatval(environ(sprintf('redis.%s.timeout', $connection), 0.0));
        $prefix = $options['prefix'] ?? strval(environ(sprintf('redis.%s.prefix', $connection), ''));
        $persistent = $options['persistent'] ?? boolval(environ(sprintf('redis.%s.persistent', $connection), false));
        $select = intval($select ?: environ(sprintf('redis.%s.select', $connection), 0));//默认选择库
        try {
            if ($persistent) {
                $instance->pconnect($host, $port, $timeout, sprintf('persistent_id_%d', $select));
            } else {
                $instance->connect($host, $port, $timeout);
            }
            if ($password) {
                $instance->auth($password);
            }
            $instance->select($select);
            $instance->setOption(\Redis::OPT_PREFIX, $prefix);
        } catch (\RedisException $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
        return $instance;
    }
	
	
    /**
     * 返回redis键
     * 建议输入 __METHOD__, 关键索引值
     * @return string
     */
    public static function key():string
    {
        $args = func_get_args();
        $replace = ['\\', ':'];
        $keys = [];
        foreach ($args as $arg){
            $keys[] = str_replace($replace, '_', $arg);
        }
        return join('_', $keys);
    }
}
