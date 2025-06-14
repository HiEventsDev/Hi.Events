import { i18n } from "@lingui/core";
import { I18nProvider } from "@lingui/react";
import { MantineProvider } from "@mantine/core";
import { ModalsProvider } from "@mantine/modals";
import { Notifications } from "@mantine/notifications";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import React, { FC, PropsWithChildren, useEffect } from "react";
import { Helmet, HelmetProvider } from "react-helmet-async";

import "@mantine/charts/styles.css";
import "@mantine/core/styles.css";
import "@mantine/core/styles/global.css";
import "@mantine/dropzone/styles.css";
import "@mantine/notifications/styles.css";
import "@mantine/tiptap/styles.css";
import { StartupChecks } from "./StartupChecks.tsx";
import { ThirdPartyScripts } from "./components/common/ThirdPartyScripts";
import "./styles/global.scss";
import { isSsr } from "./utilites/helpers.ts";
import { getConfig } from "./utilites/config.ts";

declare global {
	interface Window {
		hievents: Record<string, string>;
	}
}

export const App: FC<
	PropsWithChildren<{
		queryClient: QueryClient;
		locale: string;
		helmetContext?: any;
	}>
> = (props) => {
	const [isLoadedOnBrowser, setIsLoadedOnBrowser] = React.useState(false);

	useEffect(() => {
		setIsLoadedOnBrowser(!isSsr());
	}, []);

	return (
		<React.StrictMode>
			<div
				className="ssr-loader"
				style={{
					top: 0,
					left: 0,
					right: 0,
					bottom: 0,
					margin: 0,
					padding: 0,
					width: "100vw",
					height: "100vh",
					position: "fixed",
					background: "#ffffff",
					zIndex: 1000,
					display: isLoadedOnBrowser ? "none" : "block",
				}}
			/>
			<MantineProvider
				theme={{
					colors: {
						purple: [
							"#8260C6",
							"#734DBF",
							"#6741B2",
							"#5E3CA1",
							"#563792",
							"#4E3284",
							"#472E78",
							"#40296C",
							"#392562",
							"#332158",
						],
					},
					primaryColor: "purple",
					fontFamily: "'Varela Round', sans-serif",
				}}
			>
				<HelmetProvider context={props.helmetContext}>
					<I18nProvider i18n={i18n}>
						<QueryClientProvider client={props.queryClient}>
							<StartupChecks />
							<ThirdPartyScripts />
							<ModalsProvider>
								<Helmet>
									<title>{getConfig("VITE_APP_NAME", "Hi.Events")}</title>
								</Helmet>
								{props.children}
							</ModalsProvider>
							<Notifications />
						</QueryClientProvider>
					</I18nProvider>
				</HelmetProvider>
			</MantineProvider>
		</React.StrictMode>
	);
};
