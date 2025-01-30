import React, { useState, useEffect } from "react";
// Importing necessary libraries and components
import {
  WagmiProvider,
  createConfig,
  useAccount,
  useDisconnect,
  http
} from "wagmi";
import { ConnectKitProvider, getDefaultConfig, useModal } from "connectkit";
import {  
  CustomConnectButton,
  Loader
} from "../component/helper";
import { getBalance } from '@wagmi/core'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

// Fetching settings from window object
const settings = window.wc.wcSettings.getPaymentMethodData( 'cpmw' );
const {const_msg,networkName,rpcUrl } = settings;

// Component to handle balance and connection
const BalanceAndConnect = ({ currentchain, config, switchModal, switchHandler }) => {  
  const { disconnect } = useDisconnect();
  const {  isConnected, address, chain} = useAccount(); 
  const [triggered, setTriggered] = useState(false);
  const { open, setOpen, openSwitchNetworks } = useModal();

  // Effect to handle connection status
  useEffect(() => {       
    currentchain.getbalance(null,isConnected)
  }, [!isConnected]);

  if(!chain && isConnected && switchModal){
    if(open){
      setTimeout(()=>{
        switchHandler();
      },100)
    }
    
    openSwitchNetworks();
  }

  // Effect to handle chain id and modal open status
  useEffect(() => {
    if (chain?.id === currentchain.networks.id) {
      setOpen(false);
    }
    if(!open){
      setTriggered(false)
    }
  }, [chain?.id,open]);

  // Effect to handle modal open status and trigger status
  useEffect(() => {
    if (open) {
      setTimeout(() => {     
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
      }, 10);
    }
  }, [open,triggered]);

  // Component to fetch balance
  const FetchBalanceblock = (props) => { 
    const [balance, setBalance] = useState(null);
    const [insufficientBalance, setInsufficientBalance] = useState(false);

    // Function to fetch balance in decimal
    const fetchDecimalBalance = async () => {
      try {
        const result = await getBalance(config,{
          address: address,
          token: props.data
            ? props.data.networkResponse.contract_address[props.data.networks.id]
            : false,
        });
        props.data.getbalance(result,isConnected)
        setBalance(result)
      } catch (error) {
        console.log(error)
      }
    };
  
    // Effect to handle connection status, network id and chain id
    useEffect(() => {
      if (isConnected) {
        setBalance(null);
        if (chain?.id == props.data.networks.id) {
          fetchDecimalBalance();
        }
      }
    }, [isConnected, props.data.networks.id, chain?.id]);
  
    // Effect to handle selected gateway, balance, rating and connection status
    useEffect(() => {    
        setTimeout(() => {
          if (balance && isConnected) {
            const isInsufficient =parseFloat(balance.formatted) < parseFloat(props.data.currentprice.rating);
            setInsufficientBalance(isInsufficient);
          } else {
            setInsufficientBalance(false);
          }
        }, 100);     
    }, [     
      balance,
      props.data.currentprice?.rating,
      isConnected,
    ]);

    // Render balance and connection status
    return (
      <>
        {isConnected ? (
          balance !== null ? (
            <>
              {!insufficientBalance && (
                <div className="cpmwp_payment_notice">                 
                  {const_msg.payment_notice}
                </div>
              )}
              {insufficientBalance && (
                <div className="cpmwp_insufficient_blnc">
                  {const_msg.insufficent}
                </div>
              )}         
            </>
          ) : (
            <Loader loader={1} width={250} />
          )
        ) : null}
      </>
    );
  };

  // Render connection status and balance
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

      {isConnected && <FetchBalanceblock  data={currentchain} const_msg={const_msg} />}
      {!isConnected && <CustomConnectButton const_msg={const_msg} />}
    </>
  );
};

const queryClient = new QueryClient();

// Function to create custom config
const createCustomConfig = (props) => {
  const customConfig = getDefaultConfig({ 
    appName: props.appName,
    chains: [props.chains],
    appDescription: props.appDescription,
    appUrl: props.appUrl,
    appIcon: props.appIcon,
    transports: {
      [props.chains.id]: http(props.rpcUrl),
    },
  });

  if (customConfig) {
    const connectors = ["metaMask"];
    const newConnector = customConfig.connectors.filter((item) => connectors.includes(item.id));
    customConfig.connectors = newConnector;
    return createConfig(customConfig);
  }
};

// Main App component
const App = (props) => {
  try {
    const [config, setConfig] = useState(null);
    const [switchModal,setSwitchModal]=useState(true);
    
    const updateSwitchModalHandler=()=>{
      setSwitchModal(false);
    }

    // Effect to handle network changes
    useEffect(() => {
      const configProps = {
        appName: "Pay With Metamask",
        appDescription: window.location.host,
        chains: props.networks,
        appUrl: window.location.host,
        appIcon: "https://family.co/logo.png",
        rpcUrl: rpcUrl
      };
      setConfig(createCustomConfig(configProps));
    }, [props.networks]);

    // Render the app
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
                <BalanceAndConnect config={config} currentchain={props} switchModal={switchModal} switchHandler={updateSwitchModalHandler}/>
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
