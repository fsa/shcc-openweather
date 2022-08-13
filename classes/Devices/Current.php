<?php

namespace FSA\OpenWeatherPlugin\Devices;

use FSA\SmartHome\DeviceInterface;

class Current implements DeviceInterface
{
    const OWM_URL = 'https://api.openweathermap.org/data/2.5/';

    private $hwid;
    private $api_key;
    private $city_id;
    private $weather;
    private $events;
    private $updated;

    public function __construct()
    {
        $this->updated = 0;
    }

    public function getEventsList(): array
    {
        return ['temperature', 'humidity', 'pressure', 'wind_speed', 'wind_direction'];
    }

    public function getDescription(): string
    {
        return 'Текущая погода с OpenWeatherMap.org';
    }

    public function getHwid(): string
    {
        return $this->hwid;
    }

    public function getState(): array
    {
        if (is_null($this->weather)) {
            return [];
        }
        return [
            'temperature' => round($this->getTemperature(), 1),
            'temp_feels_like' => round($this->getTempFeelsLike(), 1),
            'humidity' => round($this->getHumidity()),
            'pressure' => $this->getPressure(),
            'description' => $this->weather->weather[0]->description,
            'wind_speed' => $this->weather->wind->speed,
            'wind_direction' => $this->weather->wind->deg,
            'wind_direction_string' => $this->getWindDirection()
        ];
    }

    public function __toString(): string
    {
        if (is_null($this->weather)) {
            return 'Информация о погоде отсутствует';
        }
        return $this->getTemperature() . '(' . $this->getTempFeelsLike() . ')&deg;C, ' . $this->getHumidity() . '%, ' . $this->getPressure() . '&nbsp;мм.рт.ст., ' . $this->weather->weather[0]->description . ', ветер ' . $this->weather->wind->speed . ' м/с, направление ' . $this->getWindDirection() . ' (' . $this->weather->wind->deg . ')';
    }

    public function getInitDataList(): array
    {
        return ['api_key' => 'Ключ API', 'city_id' => 'ID города'];
    }

    public function getInitDataValues(): array
    {
        return ['api_key' => $this->api_key, 'city_id' => $this->city_id];
    }

    public function getLastUpdate(): int
    {
        return $this->updated;
    }

    public function init($device_id, $init_data): void
    {
        $this->hwid = $device_id;
        foreach ($init_data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function update(): bool
    {
        if (is_null($this->api_key)) {
            return false;
        }
        if (is_null($this->city_id)) {
            return false;
        }
        $weather = $this->fetch();
        if (is_null($weather)) {
            return false;
        }
        if ($this->updated == $weather->dt) {
            return false;
        }
        $this->weather = $weather;
        $this->updated = $weather->dt;
        $this->events = ['temperature' => $weather->main->temp, 'humidity' => $weather->main->humidity, 'pressure' => round($weather->main->pressure * 76000 / 101325, 2), 'wind_speed' => $this->weather->wind->speed, 'wind_direction' => $this->weather->wind->deg, 'weather' => json_encode($weather, JSON_UNESCAPED_UNICODE)];
        return true;
    }

    public function getEvents(): ?array
    {
        if (empty($this->events)) {
            return null;
        }
        $events = $this->events;
        $this->events = null;
        return $events;
    }

    public function getTemperature()
    {
        return isset($this->weather->main->temp) ? $this->weather->main->temp : null;
    }

    public function getTempFeelsLike()
    {
        return isset($this->weather->main->temp) ? $this->weather->main->feels_like : null;
    }

    public function getHumidity()
    {
        return isset($this->weather->main->humidity) ? $this->weather->main->humidity : null;
    }

    public function getPressure()
    {
        return isset($this->weather->main->pressure) ? round($this->weather->main->pressure * 76000 / 101325, 2) : null;
    }

    public function getWindSpeed()
    {
        if (!isset($this->weather->wind->speed)) {
            return '-';
        }
        return $this->weather->wind->speed;
    }

    public function getWindDirection()
    {
        if (!isset($this->weather->wind->deg)) {
            return '-';
        }
        $deg = $this->weather->wind->deg;
        if ($deg < 22) {
            return 'C';
        } elseif ($deg < 68) {
            return 'СЗ';
        } elseif ($deg < 112) {
            return 'З';
        } elseif ($deg < 158) {
            return 'ЮЗ';
        } elseif ($deg < 202) {
            return 'Ю';
        } elseif ($deg < 248) {
            return 'ЮВ';
        } elseif ($deg < 292) {
            return 'В';
        } elseif ($deg < 338) {
            return 'СВ';
        } else {
            return 'С';
        }
    }

    public function getWeather() {
        return $this->weather;
    }

    private function fetch()
    {
        $params['APPID'] = $this->api_key;
        $params['id'] = $this->city_id;
        $params['units'] = 'metric';
        $params['lang'] = 'ru';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::OWM_URL . 'weather?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $weather = curl_exec($ch);
        curl_close($ch);
        if ($weather === false) {
            return null;
        }
        return json_decode($weather);
    }
}
