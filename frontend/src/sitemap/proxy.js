import axios from 'axios';

const getBackendUrl = () => {
    const backendUrl = process.env.VITE_API_URL_SERVER;
    if (!backendUrl) {
        throw new Error('VITE_API_URL_SERVER environment variable is not set');
    }
    return backendUrl;
};

const fetchSitemap = async (path, res, errorContext) => {
    try {
        const backendUrl = getBackendUrl();
        const response = await axios.get(`${backendUrl}/public${path}`, {
            headers: { 'Accept': 'application/xml' },
            responseType: 'text',
        });

        res.setHeader('Content-Type', 'application/xml');
        if (response.headers['cache-control']) {
            res.setHeader('Cache-Control', response.headers['cache-control']);
        }
        res.status(200).send(response.data);
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            res.status(404).send('Sitemap not found');
            return;
        }
        console.error(`Error fetching ${errorContext}:`, error);
        res.status(500).send('Internal server error');
    }
};

const validatePageParam = (page, res) => {
    if (!page || !/^\d+$/.test(page)) {
        res.status(400).send('Invalid page parameter');
        return false;
    }
    return true;
};

export const sitemapIndexHandler = async (_req, res) => {
    await fetchSitemap('/sitemap.xml', res, 'sitemap index');
};

export const sitemapEventsHandler = async (req, res) => {
    const { page } = req.params;
    if (!validatePageParam(page, res)) return;
    await fetchSitemap(`/sitemap-events-${page}.xml`, res, 'sitemap events');
};

export const sitemapOrganizersHandler = async (req, res) => {
    const { page } = req.params;
    if (!validatePageParam(page, res)) return;
    await fetchSitemap(`/sitemap-organizers-${page}.xml`, res, 'sitemap organizers');
};
