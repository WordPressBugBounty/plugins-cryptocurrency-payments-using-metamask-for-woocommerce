import React, { useState, useEffect } from "react";
import {
  useWaitForTransactionReceipt,
  useAccount,
  useTransaction,
  useSimulateContract,
  useWriteContract,
  useReadContracts,
} from "wagmi";
import { parseEther, erc20Abi } from "viem";
import {
  displayPopUp,
  getDynamicTransactionData,  
  PaymentInProcess,
  ConfirmTransaction,
  handleSwitchNetworkMessage,
  PaymentUi,
  PaymentLoader,
  FetchPrevTx,
  PaymentPendingUi,
} from "../component/helper";
import {
  restApiSaveTransaction,
  restApiConfirmTransaction,
  cancelOrder,
  restApiSaveTransactionHash,
} from "../component/handelRestApi";

import { useModal } from "connectkit";

export default function SendTokens({config: walletConfig}) {
  const {
    receiver,
    in_crypto,
    process_msg,
    wallet_image,
    const_msg,
    block_explorer,
    is_paid,
    order_status,
    currency_symbol,
    connectedWallet,
    confirm_msg,
    network_name,
    place_order_btn,
    token_address,
    fiatSymbol,
    totalFiat,
    decimalchainId,
    without_discount,
    currency_logo,
  } = extradataRest;

  // State variables
  const [getSaveResponse, setSaveResponse] = useState(null);
  const [runOnce, setRunOnce] = useState(null);
  const [runOnceWallet, setRunOnceWallet] = useState(null);
  const [rejectedTransaction, setRejectedTransaction] = useState(null);

  const [saveHash, setSaveHash] = useState(false);
  const [prevPaymentHash, setPrevPaymentHash] = useState(false);
  const [prevPaymentFetch, setPrevPaymentFetch] = useState(false);

  // Hooks
  const { open, setOpen } = useModal();
  const { address, chain } = useAccount();

  if (open && chain?.id !== decimalchainId) {
    handleSwitchNetworkMessage(const_msg);
  }
  //Hide the chain change popup
  useEffect(() => {
    if (chain?.id === decimalchainId) {
      setOpen(false);
    }
  }, [chain?.id]);

  const tokenDecimals = useReadContracts({
    allowFailure: false,
    contracts: [
      {
        address: token_address,
        abi: erc20Abi,
        functionName: 'decimals',
      },
      {
        address: token_address,
        abi: erc20Abi,
        functionName: 'symbol',
      },
    ]
  })

  const config = useSimulateContract({
    address: token_address,
    abi: erc20Abi,
    functionName: "transfer",
    args: [receiver, parseEther(in_crypto)],
    enabled: true,
  });

  const { writeContract, data , error } = useWriteContract()
  //Get initilas transaction details using hash
  const saveHashResponse = useTransaction({ hash: data });

  // Confirm transaction callback function
  const confirmTransaction = (txData, saveResponse) => {
    const response = getDynamicTransactionData(
      txData,
      chain?.id,
      currency_symbol,
      tokenDecimals?.data?.[0]
    );

    restApiConfirmTransaction(response, saveResponse, extradataRest);
  }

  // Fetch previous transaction status if exist then complete previous transaction.
  useEffect(async () => {
    if (!prevPaymentFetch) {
      const staticData = {
        from: address.toLowerCase(),
        amount: in_crypto,
        recever: receiver.toLowerCase(),
        token_address: token_address.toLowerCase()
      };

      const proccessData={wallet_image,process_msg,block_explorer,const_msg,config: walletConfig};

      const data= await FetchPrevTx(staticData,confirmTransaction,extradataRest,proccessData);

      data.status === true && setPrevPaymentFetch(true);
      data.txId && setPrevPaymentHash(data.txId);

    }
  }, [])

  // Save hash response & sender id in order page.
  useEffect(() => {
    if (data && !runOnce && !saveHash) {
      const staticData = {
        hash: data,
        from: address.toLowerCase(),
        amount: in_crypto,
        recever: receiver.toLowerCase(),
        token_address: token_address.toLowerCase()
      };

      restApiSaveTransactionHash(staticData, extradataRest).then(() => {
        setSaveHash(true);
      })
    }
  }, [data && !saveHashResponse.data])

  //Save the initial transaction detilas in database
  useEffect(() => {
    if (data && !runOnce && saveHashResponse.data) {
      PaymentInProcess(
        wallet_image,
        process_msg,
        block_explorer,
        saveHashResponse,
        const_msg
      );
      const response = getDynamicTransactionData(
        saveHashResponse.data,
        chain?.id,
        currency_symbol
      );
      restApiSaveTransaction(response, extradataRest).then(function (backData) {
        setSaveResponse(backData);
        setRunOnce(true);
      });
    }
  }, [data && saveHashResponse.data]);
  //Wait for transaction completetion
  const waitFordata = useWaitForTransactionReceipt({
    hash: data,
  });
  // Get confirmed transaction details using hash
  const saveConfirmResponse = useTransaction({
    hash: waitFordata.data?.transactionHash,
  });
  //Confirm the transaction & process order after block confirmation
  useEffect(() => {
    if (waitFordata.data?.transactionHash && getSaveResponse) {
      setTimeout(()=>{
        confirmTransaction(saveConfirmResponse.data, getSaveResponse);
      },3000);
    }
  }, [saveConfirmResponse.data && getSaveResponse]);

  //if any error occur during payment process
  useEffect(() => {
    if (error) {
      if (error?.shortMessage ==='User rejected the request.') {
      cancelOrder(extradataRest);
      setRejectedTransaction(true);
    } else {
      displayPopUp({
        msg: error?.shortMessage,
        image: wallet_image,
        time: 5000,
      });
    }
    }
  }, [error]);

  useEffect(() => {
    const isPageReloaded =
      performance.getEntriesByType("navigation")[0].type === "reload";

    if (isPageReloaded) {
    } else {
      if (
        !prevPaymentHash &&
        prevPaymentFetch &&
        config?.data?.request &&
        !is_paid &&
        order_status !== "cancelled" &&
        !runOnceWallet &&
        !open
      ) {
        handleTransaction();
        setRunOnceWallet(true);
      }
    }
  }, [config?.data?.request,prevPaymentFetch]);

  const handleTransaction = () => {
      ConfirmTransaction(wallet_image, confirm_msg, const_msg);
      writeContract(config?.data?.request)
  };

  return (
    <>
     {prevPaymentFetch && prevPaymentHash &&
        <PaymentPendingUi
          wallet_image={wallet_image}
          connectedWallet={connectedWallet}
          const_msg={const_msg}
          address={address}
          txId={prevPaymentHash}
        />}

      {prevPaymentFetch && !prevPaymentHash && !is_paid &&
        order_status !== "cancelled" &&
        !waitFordata.isSuccess &&
        !rejectedTransaction && (
          <PaymentUi
            wallet_image={wallet_image}
            connectedWallet={connectedWallet}
            const_msg={const_msg}
            address={address}
            without_discount={without_discount}
            currency_symbol={currency_symbol}
            in_crypto={in_crypto}
            network_name={network_name}
            currency_logo={currency_logo}
            fiatSymbol={fiatSymbol}
            totalFiat={totalFiat}
            place_order_btn={place_order_btn}
            handleTransaction={handleTransaction}
            cancelOrder={cancelOrder}
          />
        )}

      {(rejectedTransaction || !prevPaymentFetch) && (
        <PaymentLoader/>
      )}
    </>
  );
}
