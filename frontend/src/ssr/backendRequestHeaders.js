import net from 'node:net';
import proxyAddr from 'proxy-addr';

const CLOUDFLARE_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

const LICENCE_SIMULATION_HEADER = 'x-hi-licence-simulation';

const trustProxy = proxyAddr.compile(['loopback', 'linklocal', 'uniquelocal', ...CLOUDFLARE_RANGES]);

const normalizeIp = (value) => {
    if (typeof value !== 'string') {
        return null;
    }

    const candidate = value.trim().replace(/^::ffff:(?=\d{1,3}(\.\d{1,3}){3}$)/i, '');

    return net.isIP(candidate) ? candidate : null;
};

const headerValue = (req, name) => {
    const value = req.headers?.[name];
    return Array.isArray(value) ? value[0] : value;
};

export const resolveClientIp = (req) => {
    try {
        return normalizeIp(proxyAddr(req, trustProxy));
    } catch {
        return null;
    }
};

export const backendRequestHeaders = (req) => {
    const clientIp = resolveClientIp(req);

    if (!clientIp) {
        return {};
    }

    const secret = process.env.APP_SSR_SHARED_SECRET;

    return secret
        ? {'X-Forwarded-For': clientIp, 'X-Hi-Ssr-Key': secret}
        : {'X-Forwarded-For': clientIp};
};

export const licenceSimulation = (req) => headerValue(req, LICENCE_SIMULATION_HEADER) || undefined;
