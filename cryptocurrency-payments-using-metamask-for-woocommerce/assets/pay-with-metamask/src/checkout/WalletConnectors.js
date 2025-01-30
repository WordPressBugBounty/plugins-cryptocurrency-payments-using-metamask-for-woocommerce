import React, { useState, useEffect } from "react";
import {
  WagmiProvider,
  createConfig,
  useAccount,
  useDisconnect,
  http
} from "wagmi";
import { ConnectKitProvider, getDefaultConfig, useModal } from "connectkit";
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import {
  FetchBalance,
  CustomConnectButton
} from "../component/helper";

const {const_msg,networkName,rpcUrl } = connect_wallts;

const BalanceAndConnect = ({ currentchain, config, switchModal, switchHandler}) => {
  const [triggered, setTriggered] = useState(false);
  const { open, setOpen, openSwitchNetworks } = useModal();
  const { isConnected, address, chain } = useAccount();
  const { disconnect } = useDisconnect();

  if (!chain && isConnected && switchModal) {
    if (open) {
      setTimeout(() => {
        switchHandler();
      }, 100)
    }

    openSwitchNetworks();
  }

  useEffect(() => {
    if (chain?.id === currentchain.networks.id) {
      setOpen(false);
    }
    if(!open){
      setTriggered(false)
    }
  }, [chain?.id,open]);
 

  if (open) {
    const switchPage = document.querySelector(".sc-dcJsrY div");
    const switchNetworkmsg = document.querySelector(".sc-imWYAI");
    const triggerWallet = document.querySelector("#__CONNECTKIT__ button.sc-bypJrT");
  if(triggerWallet&&!triggered){
    triggerWallet.click();
    setTriggered(true)
  }
    if (
      switchNetworkmsg &&
      switchPage.firstChild.textContent == "Switch Networks"
    ) {
      switchNetworkmsg.textContent = const_msg.switch_network_msg;
    }
  }


  return (
    <>
    {!isConnected && <div className="cpmw_selected_wallet"><div className="cpmw_p_network"><strong>{const_msg.select_network}:</strong>{networkName}</div></div>}
      {chain && isConnected && (
        <>
          <div className="cpmw_p_connect">
            <div className="cpmw_p_status">{const_msg.connected}</div>
            <div
              className="cpmw_disconnect_wallet"
              onClick={() => {
                disconnect();
              }}
            >
              {const_msg.disconnect}
            </div>
          </div>
          <div className="cpmw_p_info">
            <div className="cpmw_address_wrap">
              <strong>{const_msg.wallet}:</strong>
              <span className="cpmw_p_address">{address}</span>
            </div>
            <div className="cpmw_p_network">
              <strong>{const_msg.network}:</strong>{" "}
              {currentchain.networkResponse.decimal_networks[chain?.id]
                ? currentchain.networkResponse.decimal_networks[chain?.id]
                : chain.name}
            </div>
          </div>
        </>
      )}

      <FetchBalance data={currentchain} const_msg={const_msg} config={config}/>
      {!isConnected && <CustomConnectButton const_msg={const_msg} />}
    </>
  );
};

const createCustomConfig = (props) => {
  // Modify the default configuration based on props
  const customConfig = getDefaultConfig({ 
    //alchemyId: 'vE0lCPXbzgBGR3sU4Y68JHmBNsDYBf7S',
    //walletConnectProjectId: 'ceaf5fd4fffbd074191feccca6bbb761',
    appName: props.appName,
    chains: [props.chains],
    appDescription: props.appDescription,
    appUrl: props.appUrl,
    appIcon: props.appIcon,
    transports: {
      [props.chains.id]: http(props.rpcUrl)
    }
  });

  if (customConfig) {
    const connectors = [];
 
    connectors.push("metaMask");


    //Elements to remove: 'metaMask', 'walletConnect', 'coinbaseWallet', 'injected'
    const newConnector = customConfig.connectors.filter((item) => {
      if (connectors.includes(item.id)) {      
        return connectors.includes(item.id);
      }
    });

    customConfig.connectors = newConnector;

    return createConfig(customConfig);
  }
};
const queryClient = new QueryClient();

const App = (props) => {
  try {
    const [config, setConfig] = useState(null);
    const [switchModal, setSwitchModal] = useState(true);

    const updateSwitchModalHandler = () => {
      setSwitchModal(false);
    }

    useEffect(() => {
      // Define props to be passed to the config
      const configProps = {
        //walletConnectProjectId: ccpw_wc_id,
        appName: "Pay With Metamask",
        appDescription: window.location.host,
        chains: props.networks,
        appUrl: window.location.host, // your app's URL
        appIcon: "https://family.co/logo.png", // your app's icon URL
        rpcUrl: rpcUrl,
        // Add other props as needed
      };
      setConfig(createCustomConfig(configProps));
    }, [props.networks]);

    return (
      <>
        {config && props.networks && (
          <WagmiProvider config={config}>
            <QueryClientProvider client={queryClient}>
              <ConnectKitProvider
                options={{
                  hideBalance: true,             
                  hideQuestionMarkCTA:true,
                }}
                mode="auto"
              >
                <BalanceAndConnect currentchain={props} config={config} switchModal={switchModal} switchHandler={updateSwitchModalHandler} />
              </ConnectKitProvider>
            </QueryClientProvider>
          </WagmiProvider>
        )}
      </>
    );
  } catch (error) {
    console.log(error);
  }
};

export default App;
