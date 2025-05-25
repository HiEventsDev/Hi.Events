import { getConfig } from "./config";

export function getBasePath() {
	const frontendUrl: string = getConfig( "VITE_FRONTEND_URL" ) as string || import.meta.env.VITE_FRONTEND_URL as string;
	
	try {
		const url = new URL(frontendUrl);
		let basePath: string = url.pathname;

		// Make sure it always ends without trailing slash (except root)
		if (basePath !== "/" && basePath.endsWith("/")) {
			basePath = basePath.slice(0, -1);
		}

		return basePath || "/";
	} catch ( e ) {
		// If URL parsing fails, fallback to root
		console.warn(
			`Invalid frontend URL: ${frontendUrl}. This might be due to an incorrect environment variable 'VITE_FRONTEND_URL'.`, e
		);

		return "/";
	}
}
